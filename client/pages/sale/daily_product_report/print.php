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

    /*
    |--------------------------------------------------------------------------
    | CURRENCY
    |--------------------------------------------------------------------------
    */

    $currency_sql = "
        SELECT c.symbol
        FROM tenant_currencies tc

        JOIN ledgerone_public.currencies c
            ON tc.currency_id = c.id

        WHERE tc.tenant_id = ?
          AND tc.is_base_currency = 1

        LIMIT 1
    ";

    $currency_stmt = $pdo->prepare($currency_sql);
    $currency_stmt->execute([$tenant_id]);

    $currency = $currency_stmt->fetch(PDO::FETCH_ASSOC);

    $currency_symbol = $currency['symbol'] ?? '$';


    /*
    |--------------------------------------------------------------------------
    | REPORT QUERY
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | batch_no and batch_expiry_date are directly inside
    | sale_invoice_items (sii).
    |
    | NO stock_ledger JOIN is required.
    |
    */

    $sql = "
        SELECT

            si.id AS invoice_id,

            si.bill_no,

            si.sale_date,

            c.customer_name,

            COALESCE(
                b.branch_name,
                'No Branch'
            ) AS branch_name,

            p.code AS product_code,

            p.name AS product_name,

            sii.batch_no,

            sii.batch_expiry_date,

            sii.quantity,

            COALESCE(
                u.uom_name,
                ''
            ) AS unit_symbol,

            sii.sale_price,

            sii.discount_amount,

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


        LEFT JOIN branches b
            ON si.branch_id = b.id


        LEFT JOIN uom u
            ON sii.uom_id = u.id


        WHERE sii.tenant_id = ?

          AND si.status = 'Posted'
    ";

    $params = [$tenant_id];


    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    */

    if (!empty($from_date)) {

        $sql .= "
            AND si.sale_date >= ?
        ";

        $params[] = $from_date;
    }


    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    */

    if (!empty($to_date)) {

        $sql .= "
            AND si.sale_date <= ?
        ";

        $params[] = $to_date;
    }


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER
    |--------------------------------------------------------------------------
    */

    if (!empty($customer_id)) {

        $sql .= "
            AND si.customer_id = ?
        ";

        $params[] = $customer_id;
    }


    /*
    |--------------------------------------------------------------------------
    | BRANCH
    |--------------------------------------------------------------------------
    */

    if (!empty($branch_id)) {

        $sql .= "
            AND si.branch_id = ?
        ";

        $params[] = $branch_id;
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT
    |--------------------------------------------------------------------------
    */

    if (!empty($product_id)) {

        $sql .= "
            AND p.id = ?
        ";

        $params[] = $product_id;
    }


    /*
    |--------------------------------------------------------------------------
    | BATCH NUMBER
    |--------------------------------------------------------------------------
    |
    | Batch number is directly from sii.batch_no
    |
    */

    if ($batch_no !== '') {

        $sql .= "
            AND sii.batch_no LIKE ?
        ";

        $params[] = '%' . $batch_no . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | EXPIRY DATE
    |--------------------------------------------------------------------------
    |
    | Expiry date is directly from sii.batch_expiry_date
    |
    */

    if (!empty($expiry_date)) {

        $sql .= "
            AND sii.batch_expiry_date = ?
        ";

        $params[] = $expiry_date;
    }


    /*
    |--------------------------------------------------------------------------
    | ORDER
    |--------------------------------------------------------------------------
    */

    $sql .= "
        ORDER BY
            si.sale_date ASC,
            si.bill_no ASC,
            sii.id ASC
    ";


    /*
    |--------------------------------------------------------------------------
    | EXECUTE QUERY
    |--------------------------------------------------------------------------
    */

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
| GROUP BY INVOICE
|--------------------------------------------------------------------------
*/

$groups = [];

$indexByInvoice = [];


foreach ($rows as $row) {

    $invoice_id = $row['invoice_id'];

    /*
    |--------------------------------------------------------------------------
    | NEW INVOICE
    |--------------------------------------------------------------------------
    */

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

            'items' =>
                [],

            'total_amount' =>
                0

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | ADD ITEM
    |--------------------------------------------------------------------------
    */

    $groupIndex =
        $indexByInvoice[$invoice_id];


    $groups[$groupIndex]['items'][] =
        $row;


    /*
    |--------------------------------------------------------------------------
    | INVOICE TOTAL
    |--------------------------------------------------------------------------
    */

    $groups[$groupIndex]['total_amount'] +=
        (float)($row['net_amount'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| GRAND TOTAL
|--------------------------------------------------------------------------
*/

$grand_qty = 0;

$grand_amount = 0;


foreach ($rows as $row) {

    $grand_qty +=
        (float)($row['quantity'] ?? 0);

    $grand_amount +=
        (float)($row['net_amount'] ?? 0);
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


/*
|--------------------------------------------------------------------------
| TOOLBAR
|--------------------------------------------------------------------------
*/

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


.btn-cancel {
    background: #6c757d;
}


.btn-back {
    background: #495057;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| META
|--------------------------------------------------------------------------
*/

.meta-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    font-size: 12px;

    font-style: italic;

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


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

table {

    width: 100%;

    border-collapse: collapse;

    table-layout: fixed;

    font-size: 12px;

    border:
        1px solid #000;
}


col.c-sno {
    width: 6%;
}


col.c-code {
    width: 10%;
}


col.c-detail {
    width: 25%;
}


col.c-batch {
    width: 12%;
}


col.c-expiry {
    width: 11%;
}


col.c-price {
    width: 11%;
}


col.c-qty {
    width: 11%;
}


col.c-amount {
    width: 14%;
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


/*
|--------------------------------------------------------------------------
| INVOICE HEADER
|--------------------------------------------------------------------------
*/

tr.invoice-header td {

    font-weight: normal;

    background: #f7f7f7;
}


tr.invoice-header td.bill-no {

    text-align: right;

    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

tr.total-row td {

    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| GRAND TOTAL
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

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


<!-- =========================================================
     TOOLBAR
========================================================= -->

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


<div class="sheet">


<!-- =========================================================
     HEADER
========================================================= -->

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


<!-- =========================================================
     META
========================================================= -->

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


<!-- =========================================================
     REPORT TABLE
========================================================= -->

<table id="reportTable">


<colgroup>

    <col class="c-sno">

    <col class="c-code">

    <col class="c-detail">

    <col class="c-batch">

    <col class="c-expiry">

    <col class="c-price">

    <col class="c-qty">

    <col class="c-amount">

</colgroup>


<thead>

<tr>

    <th>
        S. No.
    </th>

    <th>
        Code
    </th>

    <th>
        Detail
    </th>

    <th>
        Batch No.
    </th>

    <th>
        Expiry Date
    </th>

    <th class="num">
        Price
    </th>

    <th class="num">
        Qty
    </th>

    <th class="num">
        Amount
    </th>

</tr>

</thead>


<tbody>


<?php if (empty($groups)): ?>

<tr>

    <td
        colspan="8"
        style="text-align:center; padding:20px;"
    >
        No records found
    </td>

</tr>


<?php else: ?>


<?php foreach ($groups as $group): ?>


<!-- =========================================================
     INVOICE HEADER
========================================================= -->

<tr class="invoice-header">

    <td></td>


    <td colspan="6">

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

    </td>


    <td class="bill-no">

        <?= htmlspecialchars(
            $group['bill_no'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>

</tr>


<!-- =========================================================
     ITEMS
========================================================= -->

<?php foreach (
    $group['items']
    as $i => $row
): ?>


<tr>

    <!-- S.NO -->

    <td>
        <?= $i + 1 ?>
    </td>


    <!-- PRODUCT CODE -->

    <td>

        <?= htmlspecialchars(
            $row['product_code'] ?? '-',
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>


    <!-- PRODUCT -->

    <td>

        <?= htmlspecialchars(
            $row['product_name'] ?? '-',
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>


    <!-- BATCH -->

    <td>

        <?= htmlspecialchars(
            $row['batch_no'] ?? '-',
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </td>


    <!-- EXPIRY -->

    <td>

        <?php if (
            !empty(
                $row['batch_expiry_date']
            )
        ): ?>

            <?= htmlspecialchars(
                fmtDateShort(
                    $row['batch_expiry_date']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        <?php else: ?>

            -

        <?php endif; ?>

    </td>


    <!-- PRICE -->

    <td class="num">

        <?= htmlspecialchars(
            $currency_symbol,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

        <?= number_format(
            (float)(
                $row['sale_price'] ?? 0
            ),
            2
        ) ?>

    </td>


    <!-- QTY -->

    <td class="num">

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


    <!-- AMOUNT -->

    <td class="num">

        <?= htmlspecialchars(
            $currency_symbol,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

        <?= number_format(
            (float)(
                $row['net_amount'] ?? 0
            ),
            2
        ) ?>

    </td>

</tr>


<?php endforeach; ?>


<!-- =========================================================
     INVOICE TOTAL
========================================================= -->

<tr class="total-row">

    <td colspan="6"></td>


    <td class="num">
        Total
    </td>


    <td class="num">

        <?= htmlspecialchars(
            $currency_symbol,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

        <?= number_format(
            $group['total_amount'],
            2
        ) ?>

    </td>

</tr>


<?php endforeach; ?>


<?php endif; ?>

</tbody>

</table>


<!-- =========================================================
     GRAND TOTAL
========================================================= -->

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

    Amount

    <?= htmlspecialchars(
        $currency_symbol,
        ENT_QUOTES,
        'UTF-8'
    ) ?>

    <?= number_format(
        $grand_amount,
        2
    ) ?>

</div>

<?php endif; ?>


</div>


<script>

/*
|--------------------------------------------------------------------------
| REPORT DATA
|--------------------------------------------------------------------------
*/

const reportGroups = <?= json_encode(
    array_map(
        function ($g) {

            return [

                'date' =>
                    fmtDateShort(
                        $g['sale_date']
                    ),

                'customer' =>
                    $g['customer_name'],

                'bill_no' =>
                    $g['bill_no'],

                'total' =>
                    (float)$g['total_amount'],

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

                                'expiry' =>
                                    !empty(
                                        $r['batch_expiry_date']
                                    )
                                    ? fmtDateShort(
                                        $r['batch_expiry_date']
                                    )
                                    : '-',

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


/*
|--------------------------------------------------------------------------
| DATE FORMAT
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| EXCEL EXPORT
|--------------------------------------------------------------------------
*/

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

            /*
            |--------------------------------------------------------------------------
            | INVOICE HEADER
            |--------------------------------------------------------------------------
            */

            rows.push({

                'S.No': '',

                'Code': '',

                'Detail':
                    `${group.date}  ${group.customer}`,

                'Batch No.': '',

                'Expiry Date': '',

                'Price': '',

                'Qty': '',

                'Amount':
                    group.bill_no
            });


            /*
            |--------------------------------------------------------------------------
            | ITEMS
            |--------------------------------------------------------------------------
            */

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

                        'Expiry Date':
                            item.expiry,

                        'Price':
                            item.price.toFixed(2),

                        'Qty':
                            `${item.qty.toFixed(2)} ${
                                item.unit || ''
                            }`.trim(),

                        'Amount':
                            item.amount.toFixed(2)

                    });

                }
            );


            /*
            |--------------------------------------------------------------------------
            | INVOICE TOTAL
            |--------------------------------------------------------------------------
            */

            rows.push({

                'S.No': '',

                'Code': '',

                'Detail': '',

                'Batch No.': '',

                'Expiry Date': '',

                'Price': '',

                'Qty': 'Total',

                'Amount':
                    group.total.toFixed(2)

            });

        }
    );


    /*
    |--------------------------------------------------------------------------
    | CREATE WORKSHEET
    |--------------------------------------------------------------------------
    */

    const worksheet =
        XLSX.utils.json_to_sheet(
            rows
        );


    /*
    |--------------------------------------------------------------------------
    | COLUMN WIDTHS
    |--------------------------------------------------------------------------
    */

    worksheet['!cols'] = [

        { wch: 8 },

        { wch: 15 },

        { wch: 35 },

        { wch: 18 },

        { wch: 16 },

        { wch: 15 },

        { wch: 15 },

        { wch: 18 }

    ];


    /*
    |--------------------------------------------------------------------------
    | WORKBOOK
    |--------------------------------------------------------------------------
    */

    const workbook =
        XLSX.utils.book_new();


    XLSX.utils.book_append_sheet(

        workbook,

        worksheet,

        'Daily Product Sale'

    );


    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

    const today =
        new Date()
            .toISOString()
            .slice(0, 10);


    XLSX.writeFile(

        workbook,

        `daily_product_sale_report_${today}.xlsx`

    );

}

</script>


</body>
</html>
