<?php
// Path:
// pages/sales/daily_product_sale_report/print.php

require_once '../../../../includes/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}


/*
|--------------------------------------------------------------------------
| SMART NUMBER FORMAT
|--------------------------------------------------------------------------
|
| Used for Disc %, FOC and Tax figures. Whole numbers (5.00, 0.00) are
| shown WITHOUT the trailing decimals (5, 0). Non-whole numbers keep
| 2 decimal places (5.25). Price / Qty / Amount are left untouched
| (always 2 decimals), since those are currency-style values.
|
*/

function smartNum($num)
{
    $num = round((float)$num, 2);

    return (fmod($num, 1.0) == 0.0)
        ? number_format($num, 0)
        : number_format($num, 2);
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$from_date   = $_GET['from_date'] ?? null;
$to_date     = $_GET['to_date'] ?? null;
$customer_id = $_GET['customer_id'] ?? null;
$branch_id   = $_GET['branch_id'] ?? null;
$product_id  = $_GET['product_id'] ?? null;

$batch_no = trim($_GET['batch_no'] ?? '');

$expiry_date = $_GET['expiry_date'] ?? null;

// Salesman (sale officer) filter
$sale_officer_id = $_GET['sale_officer_id'] ?? null;

// Supplier Man (delivery man) filter
$supplier_man_id = $_GET['supplier_man_id'] ?? null;

// City filter (customer's city_id)
$city_id = $_GET['city_id'] ?? null;

// Area filter (customer's area_id)
$area_id = $_GET['area_id'] ?? null;


/*
|--------------------------------------------------------------------------
| COMPANY NAME
|--------------------------------------------------------------------------
*/

$company_name = 'COMPANY NAME';

try {

    $company_sql = "
        SELECT company_name
        FROM companies
        WHERE tenant_id = ?
          AND is_active = 1
        LIMIT 1
    ";

    $company_stmt = $pdo->prepare($company_sql);
    $company_stmt->execute([$tenant_id]);

    $company = $company_stmt->fetch(PDO::FETCH_ASSOC);

    if ($company && !empty($company['company_name'])) {
        $company_name = $company['company_name'];
    }

} catch (Exception $e) {

    error_log(
        'Print report - company lookup skipped: ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| MAIN REPORT
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT

            si.id AS invoice_id,

            si.bill_no,

            si.sale_date,

            si.net_amount AS invoice_net_amount,

            (si.total_discount_percent
                + si.extra_discount_1_percent
                + si.extra_discount_2_percent) AS invoice_disc_percent,

            (si.total_discount_amount
                + si.extra_discount_1_amount
                + si.extra_discount_2_amount) AS invoice_disc_amount,

            c.customer_name,

            COALESCE(
                ci.city_name,
                ''
            ) AS city_name,

            COALESCE(
                ar.area_name,
                ''
            ) AS area_name,

            COALESCE(
                cz.city_zone_name,
                ''
            ) AS zone_name,

            COALESCE(
                b.branch_name,
                'No Branch'
            ) AS branch_name,

            p.code AS product_code,

            p.name AS product_name,

            sii.batch_no,

            sii.batch_expiry_date,

            sii.quantity,

            sii.foc_quantity,

            COALESCE(
                u.uom_name,
                ''
            ) AS unit_symbol,

            sii.sale_price,

                      sii.discount_amount,
            sii.discount_percent,
            sii.tax_amount,

            sii.net_amount

        FROM sale_invoice_items sii


        JOIN sale_invoice si
            ON sii.sale_invoice_id = si.id
            AND sii.tenant_id = si.tenant_id


        JOIN products p
            ON sii.product_id = p.id
            AND sii.tenant_id = p.tenant_id


        JOIN customers c
            ON si.customer_id = c.id


        LEFT JOIN cities ci
            ON c.city_id = ci.id
            AND ci.tenant_id = c.tenant_id


        LEFT JOIN areas ar
            ON c.area_id = ar.id
            AND ar.tenant_id = c.tenant_id


        LEFT JOIN city_zones cz
            ON c.city_zone_id = cz.id
            AND cz.tenant_id = c.tenant_id


        LEFT JOIN branches b
            ON si.branch_id = b.id


        LEFT JOIN uom u
            ON sii.uom_id = u.id


        WHERE sii.tenant_id = ?

          AND si.status = 'Posted'
    ";

    $params = [$tenant_id];


    if (!empty($from_date)) {

        $sql .= "
            AND si.sale_date >= ?
        ";

        $params[] = $from_date;
    }


    if (!empty($to_date)) {

        $sql .= "
            AND si.sale_date <= ?
        ";

        $params[] = $to_date;
    }


    if (!empty($customer_id)) {

        $sql .= "
            AND si.customer_id = ?
        ";

        $params[] = $customer_id;
    }


    if (!empty($branch_id)) {

        $sql .= "
            AND si.branch_id = ?
        ";

        $params[] = $branch_id;
    }


    if (!empty($product_id)) {

        $sql .= "
            AND p.id = ?
        ";

        $params[] = $product_id;
    }


    if ($batch_no !== '') {

        $sql .= "
            AND sii.batch_no LIKE ?
        ";

        $params[] = '%' . $batch_no . '%';
    }


    if (!empty($expiry_date)) {

        $sql .= "
            AND sii.batch_expiry_date = ?
        ";

        $params[] = $expiry_date;
    }


    if (!empty($sale_officer_id)) {

        $sql .= "
            AND si.sale_officer_id = ?
        ";

        $params[] = $sale_officer_id;
    }


    if (!empty($supplier_man_id)) {

        $sql .= "
            AND si.supplier_man_id = ?
        ";

        $params[] = $supplier_man_id;
    }


    if (!empty($city_id)) {

        $sql .= "
            AND c.city_id = ?
        ";

        $params[] = $city_id;
    }


    if (!empty($area_id)) {

        $sql .= "
            AND c.area_id = ?
        ";

        $params[] = $area_id;
    }


    $sql .= "
        ORDER BY
            si.sale_date ASC,
            si.bill_no ASC,
            sii.id ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Exception $e) {

    error_log(
        'Daily Sale Report Print Error: ' .
        $e->getMessage()
    );

    die(
        'Error generating report: ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


/*
|--------------------------------------------------------------------------
| USER NAME
|--------------------------------------------------------------------------
*/

$user_label = 'ALL USER';

try {

    $user_sql = "
        SELECT full_name
        FROM users
        WHERE id = ?
        LIMIT 1
    ";

    $user_stmt = $pdo->prepare($user_sql);

    $user_stmt->execute([$user_id]);

    $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $user_row &&
        !empty($user_row['full_name'])
    ) {

        $user_label = $user_row['full_name'];
    }

} catch (Exception $e) {

    error_log(
        'Print report - user lookup skipped: ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| FILTER LABELS (SALESMAN / SUPPLIER MAN / CITY / AREA)
|--------------------------------------------------------------------------
*/

$salesman_name = null;

if (!empty($sale_officer_id)) {

    try {

        $salesman_sql = "
            SELECT full_name
            FROM employees
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ";

        $salesman_stmt = $pdo->prepare($salesman_sql);

        $salesman_stmt->execute([$sale_officer_id, $tenant_id]);

        $salesman_row = $salesman_stmt->fetch(PDO::FETCH_ASSOC);

        if ($salesman_row && !empty($salesman_row['full_name'])) {
            $salesman_name = $salesman_row['full_name'];
        }

    } catch (Exception $e) {

        error_log(
            'Print report - salesman lookup skipped: ' .
            $e->getMessage()
        );
    }
}


$supplier_man_name = null;

if (!empty($supplier_man_id)) {

    try {

        $supplier_man_sql = "
            SELECT full_name
            FROM employees
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ";

        $supplier_man_stmt = $pdo->prepare($supplier_man_sql);

        $supplier_man_stmt->execute([$supplier_man_id, $tenant_id]);

        $supplier_man_row = $supplier_man_stmt->fetch(PDO::FETCH_ASSOC);

        if ($supplier_man_row && !empty($supplier_man_row['full_name'])) {
            $supplier_man_name = $supplier_man_row['full_name'];
        }

    } catch (Exception $e) {

        error_log(
            'Print report - supplier man lookup skipped: ' .
            $e->getMessage()
        );
    }
}


$city_name_filter = null;

if (!empty($city_id)) {

    try {

        $city_filter_sql = "
            SELECT city_name
            FROM cities
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ";

        $city_filter_stmt = $pdo->prepare($city_filter_sql);

        $city_filter_stmt->execute([$city_id, $tenant_id]);

        $city_filter_row = $city_filter_stmt->fetch(PDO::FETCH_ASSOC);

        if ($city_filter_row && !empty($city_filter_row['city_name'])) {
            $city_name_filter = $city_filter_row['city_name'];
        }

    } catch (Exception $e) {

        error_log(
            'Print report - city lookup skipped: ' .
            $e->getMessage()
        );
    }
}


$area_name_filter = null;

if (!empty($area_id)) {

    try {

        $area_filter_sql = "
            SELECT area_name
            FROM areas
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ";

        $area_filter_stmt = $pdo->prepare($area_filter_sql);

        $area_filter_stmt->execute([$area_id, $tenant_id]);

        $area_filter_row = $area_filter_stmt->fetch(PDO::FETCH_ASSOC);

        if ($area_filter_row && !empty($area_filter_row['area_name'])) {
            $area_name_filter = $area_filter_row['area_name'];
        }

    } catch (Exception $e) {

        error_log(
            'Print report - area lookup skipped: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| FILTER SUMMARY LABEL
|--------------------------------------------------------------------------
*/

$filter_summary_parts = [];

if ($salesman_name) {
    $filter_summary_parts[] = 'Salesman: ' . $salesman_name;
}

if ($supplier_man_name) {
    $filter_summary_parts[] = 'Supplier Man: ' . $supplier_man_name;
}

if ($city_name_filter) {
    $filter_summary_parts[] = 'City: ' . $city_name_filter;
}

if ($area_name_filter) {
    $filter_summary_parts[] = 'Area: ' . $area_name_filter;
}

$filter_summary_label = implode('   |   ', $filter_summary_parts);


/*
|--------------------------------------------------------------------------
| DATE FORMAT FUNCTION
|--------------------------------------------------------------------------
*/

function fmtDateShort($dateStr)
{
    if (empty($dateStr)) {
        return '';
    }

    $timestamp = strtotime($dateStr);

    if ($timestamp === false) {
        return $dateStr;
    }

    return date(
        'd/m/y',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| BUILD "City, Area, Zone" LABEL
|--------------------------------------------------------------------------
*/

function buildLocationLabel($cityName, $areaName, $zoneName)
{
    $parts = [];

    foreach ([$cityName, $areaName, $zoneName] as $part) {

        $part = trim((string)$part);

        if ($part !== '') {
            $parts[] = $part;
        }
    }

    if (empty($parts)) {
        return '';
    }

    return ' (' . implode(', ', $parts) . ')';
}


/*
|--------------------------------------------------------------------------
| COMBINED DISCOUNT (ITEM-LEVEL + INVOICE-LEVEL)
|--------------------------------------------------------------------------
|
| Same combination used on the screen (index.php / daily-product-sale-
| report.js -> getCombinedDiscount): the ITEM-LEVEL discount (sii.
| discount_percent / discount_amount, averaged / summed across the
| invoice's line items) PLUS the INVOICE-LEVEL discount (from the
| Invoice Summary). Kept as one helper so the table row, the grand
| total, and the Excel export all agree.
|
*/

function getCombinedDiscount($group)
{
    $itemCount = count($group['items']);

    $avgItemDiscPercent = $itemCount > 0
        ? ($group['item_discount_percent_sum'] / $itemCount)
        : 0;

    return [
        'percent' => $avgItemDiscPercent + $group['invoice_disc_percent'],
        'amount'  => $group['item_discount_amount'] + $group['invoice_disc_amount']
    ];
}


/*
|--------------------------------------------------------------------------
| GROUP BY INVOICE
|--------------------------------------------------------------------------
*/

$groups = [];

$indexByInvoice = [];


foreach ($rows as $row) {

    $invoice_id = $row['invoice_id'];

    if (!isset($indexByInvoice[$invoice_id])) {

        $indexByInvoice[$invoice_id] = count($groups);

        $groups[] = [

            'invoice_id' =>
                $invoice_id,

            'bill_no' =>
                $row['bill_no'],

            'sale_date' =>
                $row['sale_date'],

            'customer_name' =>
                $row['customer_name'],

            'city_name' =>
                $row['city_name'] ?? '',

            'area_name' =>
                $row['area_name'] ?? '',

            'zone_name' =>
                $row['zone_name'] ?? '',

            'invoice_net_amount' =>
                (float)($row['invoice_net_amount'] ?? 0),

            'invoice_disc_percent' =>
                (float)($row['invoice_disc_percent'] ?? 0),

            'invoice_disc_amount' =>
                (float)($row['invoice_disc_amount'] ?? 0),

            'item_discount_amount' =>
                0,

            'item_discount_percent_sum' =>
                0,

            'items' =>
                [],

            'total_amount' =>
                0,

            'total_tax' =>
                0

        ];
    }


    $groupIndex =
        $indexByInvoice[$invoice_id];


    $groups[$groupIndex]['items'][] =
        $row;


    $groups[$groupIndex]['total_amount'] +=
        (float)($row['net_amount'] ?? 0);


    $groups[$groupIndex]['total_tax'] +=
        (float)($row['tax_amount'] ?? 0);


    // Item-level discount (per line) — combined with invoice-level
    // discount below via getCombinedDiscount().
    $groups[$groupIndex]['item_discount_amount'] +=
        (float)($row['discount_amount'] ?? 0);

    $groups[$groupIndex]['item_discount_percent_sum'] +=
        (float)($row['discount_percent'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| GRAND TOTAL
|--------------------------------------------------------------------------
*/

$grand_qty = 0;

$grand_foc_qty = 0;

$grand_amount = 0;

$grand_tax = 0;

$grand_discount = 0;


foreach ($rows as $row) {

    $grand_qty +=
        (float)($row['quantity'] ?? 0);

    $grand_foc_qty +=
        (float)($row['foc_quantity'] ?? 0);

    $grand_amount +=
        (float)($row['net_amount'] ?? 0);

    $grand_tax +=
        (float)($row['tax_amount'] ?? 0);
}


// Discount — combined item-level + invoice-level, summed once per invoice
// group (same combination as each invoice's own Discount row / the
// screen version's grand total).
foreach ($groups as $group) {

    $grand_discount +=
        getCombinedDiscount($group)['amount'];
}


/*
|--------------------------------------------------------------------------
| DATE LABELS
|--------------------------------------------------------------------------
*/

$from_label =
    !empty($from_date)
        ? fmtDateShort($from_date)
        : 'ALL';


$to_label =
    !empty($to_date)
        ? fmtDateShort($to_date)
        : 'ALL';

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="format-detection"
    content="telephone=no, date=no, address=no, email=no, url=no"
>

<title>Daily Product Sale Print</title>


<script
    src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js">
</script>


<style>

@page {
    size: A4 landscape;
    margin: 15mm;
}


* {
    box-sizing: border-box;
}


body {

    font-family:
        'Times New Roman',
        Times,
        serif;

    color: #000;

    margin: 0;

    padding: 0;

    background: #fff;
}


.sheet {

    max-width: 1200px;

    margin: 0 auto;

    padding:
        10px
        15px
        30px;
}


.toolbar {

    text-align: center;

    margin-bottom: 20px;

    display: flex;

    justify-content: center;

    gap: 10px;
}


.toolbar button {

    font-family: Arial, sans-serif;

    font-size: 13px;

    padding:
        9px
        20px;

    border: none;

    border-radius: 4px;

    cursor: pointer;

    color: #fff;
}


.btn-print {
    background: #007bff;
}


.btn-excel {
    background: #1e7e34;
}


.btn-columns {
    background: #6f42c1;
}


.btn-cancel {
    background: #6c757d;
}


.btn-back {
    background: #495057;
}


.print-header {

    text-align: center;

    margin-bottom: 6px;
}


.print-header h1 {

    margin: 0;

    font-size: 30px;

    letter-spacing: 7px;

    font-weight: bold;
}


.print-header h2 {

    margin:
        10px
        0
        0;

    font-size: 15px;

    font-weight: bold;

    letter-spacing: .5px;
}


.meta-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    font-size: 12px;


    border-top:
        1px solid #000;

    border-bottom:
        1px solid #000;

    padding:
        5px
        0;

    margin-top: 14px;
}


.meta-row .center {

    flex: 1;

    text-align: center;
}


.filter-summary-row {

    text-align: center;

    font-size: 15.5px;


    color: #333;

    padding:
        5px
        0
        0;
}


table {

    width: 100%;

    border-collapse: collapse;

    table-layout: fixed;

    font-size: 12px;

    border:
        1px solid #000;
}


col.c-sno {
    width: 5%;
}


col.c-code {
    width: 9%;
}


col.c-detail {
    width: 22%;
}


col.c-batch {
    width: 10%;
}


col.c-price {
    width: 10%;
}


col.c-qty {
    width: 9%;
}


col.c-disc {
    width: 8%;
}


col.c-foc {
    width: 8%;
}


col.c-amount {
    width: 18%;
}


thead th,
tbody td {

    border:
        1px solid #000;
}


thead th {

    padding:
        7px
        6px;

    text-align: left;

    font-weight: bold;

    background-color: #eef1f5;
}


thead th.num,
td.num {

    text-align: right;
}


tbody td {

    padding:
        5px
        6px;

    vertical-align: top;

    line-height: 1.35;

    word-wrap: break-word;
}


tr.invoice-header td {

    font-weight: normal;

    background: #f7f7f7;
}


tr.invoice-header td.bill-no {

    text-align: right;

    font-weight: bold;
}


tr.total-row td {

    font-weight: bold;
}


.grand-total {

    margin-top: 14px;

    border-top:
        2px solid #000;

    padding-top: 8px;

    font-weight: bold;

    font-size: 13px;

    text-align: right;
}


.no-print {
    display: block;
}


body.hide-col-sno    .col-sno,
body.hide-col-code   .col-code,
body.hide-col-detail .col-detail,
body.hide-col-batch  .col-batch,
body.hide-col-price  .col-price,
body.hide-col-qty    .col-qty,
body.hide-col-disc   .col-disc,
body.hide-col-foc    .col-foc,
body.hide-col-amount .col-amount {
    display: none !important;
}


.modal-overlay {

    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    font-family: Arial, sans-serif;
}

.modal-overlay.open {
    display: flex;
}

.modal-box {

    background: #fff;
    width: 320px;
    max-width: 90vw;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
}

.modal-header {

    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #6f42c1;
    color: #fff;
}

.modal-header h3 {
    margin: 0;
    font-size: 15px;
}

.modal-close {

    background: none;
    border: none;
    color: #fff;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
}

.modal-body {

    padding: 14px 16px;
    max-height: 320px;
    overflow-y: auto;
}

.modal-body label {

    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    font-size: 13px;
    color: #222;
    cursor: pointer;
}

.modal-footer {

    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #eee;
}

.modal-footer button {

    font-size: 13px;
    padding: 7px 14px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.modal-btn-reset {
    background: #e9ecef;
    color: #333;
}

.modal-btn-apply {
    background: #6f42c1;
    color: #fff;
}


@media print {

    .no-print {

        display: none !important;
    }

    .sheet {

        padding-left: 10px;

        padding-right: 10px;
    }

    table {

        font-size: 11px;
    }

    thead th,
    tbody td {

        padding:
            4px
            5px;
    }

    tr.invoice-header {

        page-break-after: avoid;
    }

    tr.total-row {

        page-break-before: avoid;
    }
}

</style>

</head>


<body>


<div class="toolbar no-print">

    <button
        class="btn-print"
        onclick="window.print()"
    >
        Print
    </button>


    <button
        class="btn-excel"
        onclick="exportToExcel()"
    >
        Export to Excel
    </button>


    <button
        class="btn-columns"
        id="columns-btn"
        type="button"
    >
        Columns
    </button>


    <button
        class="btn-cancel"
        onclick="window.close()"
    >
        Cancel
    </button>


    <button
        class="btn-back"
        onclick="window.location.href='index.php'"
    >
        Back to Page
    </button>

</div>


<div class="modal-overlay no-print" id="columns-modal-overlay">

    <div class="modal-box">

        <div class="modal-header">
            <h3>Show / Hide Columns</h3>
            <button class="modal-close" id="columns-modal-close" type="button">&times;</button>
        </div>

        <div class="modal-body" id="columns-modal-body">
            <!-- checkboxes injected by JS -->
        </div>

        <div class="modal-footer">
            <button class="modal-btn-reset" id="columns-reset-btn" type="button">Reset</button>
            <button class="modal-btn-apply" id="columns-apply-btn" type="button">Apply</button>
        </div>

    </div>

</div>


<div class="sheet">


<div class="print-header">

    <h1>
        <?= htmlspecialchars(
            strtoupper($company_name),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </h1>

    <h2>
        DAILY PRODUCT SALE
    </h2>

</div>


<div class="meta-row">

    <div>

        FROM DATE

        <?= htmlspecialchars(
            $from_label,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

        &nbsp;&nbsp;

        TO

        <?= htmlspecialchars(
            $to_label,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>


    <div class="center">

        <?= htmlspecialchars(
            strtoupper($user_label),
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>


    <div>
        Page # - 1/1
    </div>

</div>


<?php if (!empty($filter_summary_label)): ?>

<div class="filter-summary-row">

    <?= htmlspecialchars(
        $filter_summary_label,
        ENT_QUOTES,
        'UTF-8'
    ) ?>

</div>

<?php endif; ?>


<table id="reportTable">


<colgroup>

    <col class="c-sno">

    <col class="c-code">

    <col class="c-detail">

    <col class="c-batch">

    <col class="c-price">

    <col class="c-qty">

    <col class="c-disc">

    <col class="c-foc">

    <col class="c-amount">

</colgroup>


<thead>

<tr>

    <th class="col-sno">
        S. No.
    </th>

    <th class="col-code">
        Code
    </th>

    <th class="col-detail">
        Detail
    </th>

    <th class="col-batch">
        Batch No.
    </th>

    <th class="num col-price">
        Price
    </th>

    <th class="num col-qty">
        Qty
    </th>

    <th class="num col-disc">
        Disc %
    </th>

    <th class="num col-foc">
        FOC
    </th>

    <th class="num col-amount">
        Amount
    </th>

</tr>

</thead>


<tbody>


<?php if (empty($groups)): ?>

<tr>

    <td
        colspan="9"
        style="text-align:center; padding:20px;"
    >
        No records found
    </td>

</tr>


<?php else: ?>


<?php foreach ($groups as $group): ?>

<?php

$locationLabel = buildLocationLabel(
    $group['city_name'],
    $group['area_name'],
    $group['zone_name']
);

// Combined discount (item-level + invoice-level) — same as index.php's
// on-screen Discount row.
$combinedDiscount = getCombinedDiscount($group);

?>

<tr class="invoice-header">

    <td class="col-sno"></td>


    <td colspan="5" class="col-code col-detail col-batch col-price col-qty">

        <?= htmlspecialchars(
            fmtDateShort(
                $group['sale_date']
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?>

        &nbsp;&nbsp;

        <strong>

            <?= htmlspecialchars(
                $group['customer_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </strong>

        <?= htmlspecialchars(
            $locationLabel,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>


    <td class="col-disc"></td>


    <td class="col-foc"></td>


    <td class="bill-no col-amount">

        <?= number_format(
            $group['invoice_net_amount'],
            2
        ) ?>

        &nbsp;&nbsp;

        <?= htmlspecialchars(
            $group['bill_no'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>

</tr>


<?php foreach (
    $group['items']
    as $i => $row
): ?>


<tr>

    <td class="col-sno">
        <?= $i + 1 ?>
    </td>


    <td class="col-code">

        <?= htmlspecialchars(
            $row['product_code'] ?? '-',
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>


    <td class="col-detail">

        <?= htmlspecialchars(
            $row['product_name'] ?? '-',
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>


    <td class="col-batch">

        <?= htmlspecialchars(
            $row['batch_no'] ?? '-',
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>


    <td class="num col-price">

        <?= number_format(
            (float)(
                $row['sale_price'] ?? 0
            ),
            2
        ) ?>

    </td>


    <td class="num col-qty">

        <?= number_format(
            (float)(
                $row['quantity'] ?? 0
            ),
            2
        ) ?>

        <?= htmlspecialchars(
            $row['unit_symbol'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>


    <td class="num col-disc">

        <?= smartNum(
            $row['discount_percent'] ?? 0
        ) ?>%

    </td>


    <td class="num col-foc">

        <?= smartNum(
            $row['foc_quantity'] ?? 0
        ) ?>

    </td>


    <td class="num col-amount">

        <?= number_format(
            (float)(
                $row['net_amount'] ?? 0
            ),
            2
        ) ?>

    </td>

</tr>


<?php endforeach; ?>


<tr class="total-row">

    <td colspan="5" class="col-sno col-code col-detail col-batch col-price col-qty"></td>


    <td class="num col-qty" style="text-align:right;">Total</td>


    <td class="num col-disc"></td>


    <td class="num col-foc"></td>


    <td class="num col-amount">

        <?= number_format(
            $group['total_amount'],
            2
        ) ?>

    </td>

</tr>


<tr class="total-row">

    <td colspan="5" class="col-sno col-code col-detail col-batch col-price col-qty"></td>


    <td class="num col-qty" style="text-align:right;">

        <?= $combinedDiscount['percent'] > 0
            ? 'Discount (' . smartNum($combinedDiscount['percent']) . '%)'
            : 'Discount' ?>

    </td>


    <td class="num col-disc"></td>


    <td class="num col-foc"></td>


    <td class="num col-amount">

        <?= smartNum(
            $combinedDiscount['amount']
        ) ?>

    </td>

</tr>


<tr class="total-row">

    <td colspan="5" class="col-sno col-code col-detail col-batch col-price col-qty"></td>


    <td class="num col-qty" style="text-align:right;">Tax</td>


    <td class="num col-disc"></td>


    <td class="num col-foc"></td>


    <td class="num col-amount">

        <?= smartNum(
            $group['total_tax']
        ) ?>

    </td>

</tr>


<?php endforeach; ?>


<?php endif; ?>

</tbody>

</table>


<?php if (!empty($groups)): ?>

<div class="grand-total">

    Grand Total:

    <?= count($groups) ?>

    invoice(s),

    Qty

    <?= number_format(
        $grand_qty,
        2
    ) ?>,

    FOC

    <?= smartNum(
        $grand_foc_qty
    ) ?>,

    Discount

    <?= smartNum(
        $grand_discount
    ) ?>,

    Tax

    <?= smartNum(
        $grand_tax
    ) ?>,

    Amount

    <?= number_format(
        $grand_amount,
        2
    ) ?>

</div>

<?php endif; ?>


</div>


<script>

const reportGroups = <?= json_encode(
    array_map(
        function ($g) {

            $locationLabel = buildLocationLabel(
                $g['city_name'],
                $g['area_name'],
                $g['zone_name']
            );

            // Combined discount (item-level + invoice-level) — kept in
            // sync with the on-screen table above so the Excel export
            // matches what's printed.
            $combined = getCombinedDiscount($g);

            return [

                'date' =>
                    fmtDateShort(
                        $g['sale_date']
                    ),

                'customer' =>
                    $g['customer_name'] . $locationLabel,

                'bill_no' =>
                    $g['bill_no'],

                'invoice_net_amount' =>
                    (float)$g['invoice_net_amount'],

                'disc_percent' =>
                    (float)$combined['percent'],

                'disc_amount' =>
                    (float)$combined['amount'],

                'total' =>
                    (float)$g['total_amount'],

                'tax' =>
                    (float)$g['total_tax'],

                'items' =>
                    array_map(
                        function ($r) {

                            return [

                                'code' =>
                                    $r['product_code']
                                    ?? '-',

                                'name' =>
                                    $r['product_name']
                                    ?? '-',

                                'batch' =>
                                    $r['batch_no']
                                    ?? '-',

                                'price' =>
                                    (float)(
                                        $r['sale_price']
                                        ?? 0
                                    ),

                                'qty' =>
                                    (float)(
                                        $r['quantity']
                                        ?? 0
                                    ),

                                'foc' =>
                                    (float)(
                                        $r['foc_quantity']
                                        ?? 0
                                    ),

                                'unit' =>
                                    $r['unit_symbol']
                                    ?? '',

                                'amount' =>
                                    (float)(
                                        $r['net_amount']
                                        ?? 0
                                    )
                            ];

                        },
                        $g['items']
                    )
            ];

        },
        $groups
    ),
    JSON_UNESCAPED_UNICODE
) ?>;


function fmtDateShort(dateStr)
{

    if (!dateStr) {
        return '';
    }


    const d =
        new Date(dateStr);


    if (isNaN(d)) {
        return dateStr;
    }


    const dd =
        String(
            d.getDate()
        ).padStart(2, '0');


    const mm =
        String(
            d.getMonth() + 1
        ).padStart(2, '0');


    const yy =
        String(
            d.getFullYear()
        ).slice(-2);


    return `${dd}/${mm}/${yy}`;
}


function smartNum(num)
{
    let n = parseFloat(num);

    if (isNaN(n)) n = 0;

    n = Math.round(n * 100) / 100;

    return (n % 1 === 0) ? n.toFixed(0) : n.toFixed(2);
}


function exportToExcel()
{

    if (
        typeof XLSX ===
        'undefined'
    ) {

        alert(
            'Excel export library failed to load. ' +
            'Please check your internet connection and reload the page.'
        );

        return;
    }


    if (
        !reportGroups.length
    ) {

        alert(
            'No data available to export.'
        );

        return;
    }


    const rows = [];


    reportGroups.forEach(
        group => {

            rows.push({

                'S.No': '',

                'Code': '',

                'Detail':
                    `${group.date}  ${group.customer}`,

                'Batch No.': '',

                'Price': '',

                'Qty': '',

                'Disc %': '',

                'FOC': '',

                'Amount':
                    `${group.invoice_net_amount.toFixed(2)}  ${group.bill_no}`
            });


            group.items.forEach(
                (item, idx) => {

                    rows.push({

                        'S.No':
                            idx + 1,

                        'Code':
                            item.code,

                        'Detail':
                            item.name,

                        'Batch No.':
                            item.batch,

                        'Price':
                            item.price.toFixed(2),

                        'Qty':
                            `${item.qty.toFixed(2)} ${
                                item.unit || ''
                            }`.trim(),

                        'Disc %':
                            smartNum(group.disc_percent),

                        'FOC':
                            smartNum(item.foc),

                        'Amount':
                            item.amount.toFixed(2)

                    });

                }
            );


            // Total — label goes in "Detail" (not "FOC") so it doesn't get
            // confused with an actual FOC quantity when scanning the sheet.
            rows.push({

                'S.No': '',

                'Code': '',

                'Detail': 'Total',

                'Batch No.': '',

                'Price': '',

                'Qty': '',

                'Disc %': '',

                'FOC': '',

                'Amount':
                    group.total.toFixed(2)

            });


            // Discount — combined item-level + invoice-level, same as the
            // on-screen report.
            rows.push({

                'S.No': '',

                'Code': '',

                'Detail':
                    group.disc_percent > 0
                        ? `Discount (${smartNum(group.disc_percent)}%)`
                        : 'Discount',

                'Batch No.': '',

                'Price': '',

                'Qty': '',

                'Disc %': '',

                'FOC': '',

                'Amount':
                    smartNum(group.disc_amount)

            });


            rows.push({

                'S.No': '',

                'Code': '',

                'Detail': 'Tax',

                'Batch No.': '',

                'Price': '',

                'Qty': '',

                'Disc %': '',

                'FOC': '',

                'Amount':
                    smartNum(group.tax)

            });

        }
    );


    const worksheet =
        XLSX.utils.json_to_sheet(
            rows
        );


    worksheet['!cols'] = [

        { wch: 8 },

        { wch: 15 },

        { wch: 35 },

        { wch: 18 },

        { wch: 15 },

        { wch: 15 },

        { wch: 10 },

        { wch: 10 },

        { wch: 20 }

    ];


    const workbook =
        XLSX.utils.book_new();


    XLSX.utils.book_append_sheet(

        workbook,

        worksheet,

        'Daily Product Sale'

    );


    const today =
        new Date()
            .toISOString()
            .slice(0, 10);


    XLSX.writeFile(

        workbook,

        `daily_product_sale_report_${today}.xlsx`

    );

}


const COLUMN_STORAGE_KEY = 'dpsr_column_visibility';

const COLUMN_CONFIG = [
    { key: 'sno',    label: 'Sr#' },
    { key: 'code',   label: 'Product Code' },
    { key: 'detail', label: 'Details' },
    { key: 'batch',  label: 'Batch No' },
    { key: 'price',  label: 'Price' },
    { key: 'qty',    label: 'Qty' },
    { key: 'disc',   label: 'Disc %' },
    { key: 'foc',    label: 'FOC' },
    { key: 'amount', label: 'Amount' }
];

function getColumnPrefs() {
    try {
        const raw = localStorage.getItem(COLUMN_STORAGE_KEY);
        if (!raw) return {};
        return JSON.parse(raw);
    } catch (e) {
        return {};
    }
}

function saveColumnPrefs(prefs) {
    try {
        localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(prefs));
    } catch (e) {
        console.error('Could not save column preferences:', e);
    }
}

function applyColumnVisibility() {
    const prefs = getColumnPrefs();
    const body = document.body;

    COLUMN_CONFIG.forEach(col => {
        const isHidden = prefs[col.key] === false;
        body.classList.toggle(`hide-col-${col.key}`, isHidden);
    });
}

function buildColumnsModalBody() {
    const prefs = getColumnPrefs();
    const container = document.getElementById('columns-modal-body');
    if (!container) return;

    container.innerHTML = COLUMN_CONFIG.map(col => {
        const checked = prefs[col.key] === false ? '' : 'checked';
        return `
            <label>
                <input type="checkbox" data-col-key="${col.key}" ${checked}>
                ${col.label}
            </label>
        `;
    }).join('');
}

function openColumnsModal() {
    buildColumnsModalBody();
    document.getElementById('columns-modal-overlay').classList.add('open');
}

function closeColumnsModal() {
    document.getElementById('columns-modal-overlay').classList.remove('open');
}

document.addEventListener('DOMContentLoaded', () => {

    applyColumnVisibility();

    const columnsBtn = document.getElementById('columns-btn');
    const closeBtn = document.getElementById('columns-modal-close');
    const applyBtn = document.getElementById('columns-apply-btn');
    const resetBtn = document.getElementById('columns-reset-btn');
    const overlay = document.getElementById('columns-modal-overlay');

    if (columnsBtn) {
        columnsBtn.addEventListener('click', openColumnsModal);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeColumnsModal);
    }

    if (overlay) {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeColumnsModal();
        });
    }

    if (applyBtn) {
        applyBtn.addEventListener('click', () => {
            const prefs = {};
            document.querySelectorAll('#columns-modal-body input[type="checkbox"]').forEach(cb => {
                prefs[cb.getAttribute('data-col-key')] = cb.checked;
            });
            saveColumnPrefs(prefs);
            applyColumnVisibility();
            closeColumnsModal();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            saveColumnPrefs({});
            applyColumnVisibility();
            buildColumnsModalBody();
        });
    }
});

</script>


</body>
</html>