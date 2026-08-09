<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';

// ── Currency ────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT c.symbol
    FROM tenant_currencies tc
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$tenant_id]);
$currency        = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '';

// ── User ────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user      = $stmt->fetch();
$user_name = $user['full_name'] ?? 'Unknown';

// ── GET params ──────────────────────────────────────────────────────────────
$date_from    = !empty($_GET['date_from'])    ? $_GET['date_from']              : null;
$date_to      = !empty($_GET['date_to'])      ? $_GET['date_to']                : null;
$salesman_id  = !empty($_GET['salesman_id'])  ? (int)$_GET['salesman_id']       : null;
$country_id   = !empty($_GET['country_id'])   ? (int)$_GET['country_id']        : null;
$region_id    = !empty($_GET['region_id'])    ? (int)$_GET['region_id']         : null;
$city_id      = !empty($_GET['city_id'])      ? (int)$_GET['city_id']           : null;
$city_zone_id = !empty($_GET['city_zone_id']) ? (int)$_GET['city_zone_id']      : null;
$area_id      = !empty($_GET['area_id'])      ? (int)$_GET['area_id']           : null;
$company_id   = !empty($_GET['company_id'])   ? (int)$_GET['company_id']        : null;
$view_mode    = (!empty($_GET['view']) && $_GET['view'] === 'customer') ? 'customer' : 'bill';

// ── Company info ─────────────────────────────────────────────────────────────
if ($company_id) {
    $stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url, timezone FROM companies WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$company_id, $tenant_id]);
} else {
    $stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url, timezone FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$tenant_id]);
}
$company          = $stmt->fetch();
$company_name     = $company['company_name'] ?? 'Company';
$company_address  = $company['address']      ?? '';
$company_phone    = $company['phone']        ?? '';
$company_email    = $company['email']        ?? '';
$company_logo     = $company['logo_url']     ?? '';
$company_timezone = $company['timezone']     ?? 'UTC';
date_default_timezone_set($company_timezone);

// ── Filter label names ───────────────────────────────────────────────────────
$salesman_name = '';
if ($salesman_id) {
    $s = $pdo->prepare("SELECT full_name FROM employees WHERE id = ?");
    $s->execute([$salesman_id]);
    $salesman_name = $s->fetchColumn() ?: '';
}

$country_name = '';
if ($country_id) {
    $s = $pdo->prepare("SELECT country_name FROM countries WHERE id = ?");
    $s->execute([$country_id]);
    $country_name = $s->fetchColumn() ?: '';
}

$region_name = '';
if ($region_id) {
    $s = $pdo->prepare("SELECT region_name FROM regions WHERE id = ?");
    $s->execute([$region_id]);
    $region_name = $s->fetchColumn() ?: '';
}

$city_name = '';
if ($city_id) {
    $s = $pdo->prepare("SELECT city_name FROM cities WHERE id = ?");
    $s->execute([$city_id]);
    $city_name = $s->fetchColumn() ?: '';
}

$city_zone_name = '';
if ($city_zone_id) {
    $s = $pdo->prepare("SELECT city_zone_name FROM city_zones WHERE id = ?");
    $s->execute([$city_zone_id]);
    $city_zone_name = $s->fetchColumn() ?: '';
}

$area_name = '';
if ($area_id) {
    $s = $pdo->prepare("SELECT area_name FROM areas WHERE id = ?");
    $s->execute([$area_id]);
    $area_name = $s->fetchColumn() ?: '';
}

// ── Data query ───────────────────────────────────────────────────────────────
$inner = "
    SELECT
        COALESCE(e.full_name, 'Unassigned') AS salesman_name,
        e.id                                AS salesman_id,
        c.customer_name,
        c.address,
        c.primary_phone,
        obi.invoice_number                  AS bill_no,
        obi.invoice_date,
        DATEDIFF(CURDATE(), obi.invoice_date) AS days_overdue,
        obi.debit - COALESCE((
            SELECT SUM(rv.amount)
            FROM receive_voucher rv
            WHERE rv.bill_no = obi.invoice_number
              AND rv.tenant_id = ?
        ), 0)                               AS current_balance,
        COALESCE((
            SELECT SUM(rv.amount)
            FROM receive_voucher rv
            WHERE rv.customer_id = c.id
              AND rv.tenant_id = ?
            " . ($date_from ? " AND rv.voucher_date >= ?" : "") . "
            " . ($date_to   ? " AND rv.voucher_date <= ?" : "") . "
        ), 0)                               AS total_received,
        COALESCE((
            SELECT SUM(sr.net_amount)
            FROM sale_return sr
            WHERE sr.customer_id = c.id
              AND sr.tenant_id = ?
              AND sr.status = 'Posted'
            " . ($date_from ? " AND sr.sale_date >= ?" : "") . "
            " . ($date_to   ? " AND sr.sale_date <= ?" : "") . "
        ), 0)                               AS total_returns,
        COALESCE((
            SELECT SUM(rv.amount)
            FROM receive_voucher rv
            WHERE rv.customer_id = c.id
              AND rv.tenant_id = ?
              AND (rv.bill_no IS NULL OR rv.bill_no = '' OR rv.bill_no = 'null')
        ), 0)                               AS unallocated_received
    FROM opening_balance_invoices obi
    JOIN customers c ON obi.customer_id = c.id
    LEFT JOIN employees e ON obi.employee_id = e.id
    WHERE obi.tenant_id = ?
      AND c.status = 'ACTIVE'
      AND obi.debit > 0
    " . ($salesman_id  ? " AND obi.employee_id = ?"    : "") . "
    " . ($company_id   ? " AND c.company_id = ?"       : "") . "
    " . ($country_id   ? " AND c.country_id = ?"       : "") . "
    " . ($region_id    ? " AND c.region_id = ?"        : "") . "
    " . ($city_id      ? " AND c.city_id = ?"          : "") . "
    " . ($city_zone_id ? " AND c.city_zone_id = ?"     : "") . "
    " . ($area_id      ? " AND c.area_id = ?"          : "") . "

    UNION ALL

    SELECT
        COALESCE(e.full_name, 'Unassigned') AS salesman_name,
        e.id                                AS salesman_id,
        c.customer_name,
        c.address,
        c.primary_phone,
        si.bill_no,
        si.sale_date                        AS invoice_date,
        DATEDIFF(CURDATE(), si.sale_date)   AS days_overdue,
        si.net_amount - COALESCE((
            SELECT SUM(rv.amount)
            FROM receive_voucher rv
            WHERE rv.bill_no = si.bill_no
              AND rv.tenant_id = ?
        ), 0) - COALESCE((
            SELECT SUM(sr.net_amount)
            FROM sale_return sr
            WHERE sr.sale_invoice_no = si.id
              AND sr.tenant_id = ?
              AND sr.status = 'Posted'
        ), 0)                               AS current_balance,
        COALESCE((
            SELECT SUM(rv.amount)
            FROM receive_voucher rv
            WHERE rv.customer_id = c.id
              AND rv.tenant_id = ?
            " . ($date_from ? " AND rv.voucher_date >= ?" : "") . "
            " . ($date_to   ? " AND rv.voucher_date <= ?" : "") . "
        ), 0)                               AS total_received,
        COALESCE((
            SELECT SUM(sr.net_amount)
            FROM sale_return sr
            WHERE sr.customer_id = c.id
              AND sr.tenant_id = ?
              AND sr.status = 'Posted'
            " . ($date_from ? " AND sr.sale_date >= ?" : "") . "
            " . ($date_to   ? " AND sr.sale_date <= ?" : "") . "
        ), 0)                               AS total_returns,
        COALESCE((
            SELECT SUM(rv.amount)
            FROM receive_voucher rv
            WHERE rv.customer_id = c.id
              AND rv.tenant_id = ?
              AND (rv.bill_no IS NULL OR rv.bill_no = '' OR rv.bill_no = 'null')
        ), 0)                               AS unallocated_received
    FROM sale_invoice si
    JOIN customers c ON si.customer_id = c.id
    LEFT JOIN employees e ON si.sale_officer_id = e.id
    WHERE si.tenant_id = ?
      AND c.status = 'ACTIVE'
      AND si.status = 'Posted'
      AND si.net_amount > 0
    " . ($salesman_id  ? " AND si.sale_officer_id = ?"  : "") . "
    " . ($date_from    ? " AND si.sale_date >= ?"       : "") . "
    " . ($date_to      ? " AND si.sale_date <= ?"       : "") . "
    " . ($company_id   ? " AND si.company_id = ?"       : "") . "
    " . ($country_id   ? " AND c.country_id = ?"        : "") . "
    " . ($region_id    ? " AND c.region_id = ?"         : "") . "
    " . ($city_id      ? " AND c.city_id = ?"           : "") . "
    " . ($city_zone_id ? " AND c.city_zone_id = ?"      : "") . "
    " . ($area_id      ? " AND c.area_id = ?"           : "") . "
";

$query = "SELECT * FROM ($inner) AS sr_data WHERE current_balance > 0 ORDER BY salesman_name, customer_name, invoice_date";

// UNION Part 1 params
$params   = [$tenant_id];
$params[] = $tenant_id;
if ($date_from) $params[] = $date_from;
if ($date_to)   $params[] = $date_to;
$params[] = $tenant_id;
if ($date_from) $params[] = $date_from;
if ($date_to)   $params[] = $date_to;
$params[] = $tenant_id;
$params[] = $tenant_id;
if ($salesman_id)  $params[] = $salesman_id;
if ($company_id)   $params[] = $company_id;
if ($country_id)   $params[] = $country_id;
if ($region_id)    $params[] = $region_id;
if ($city_id)      $params[] = $city_id;
if ($city_zone_id) $params[] = $city_zone_id;
if ($area_id)      $params[] = $area_id;

// UNION Part 2 params
$params[] = $tenant_id;
$params[] = $tenant_id;
$params[] = $tenant_id;
if ($date_from) $params[] = $date_from;
if ($date_to)   $params[] = $date_to;
$params[] = $tenant_id;
if ($date_from) $params[] = $date_from;
if ($date_to)   $params[] = $date_to;
$params[] = $tenant_id;
$params[] = $tenant_id;
if ($salesman_id)  $params[] = $salesman_id;
if ($date_from)    $params[] = $date_from;
if ($date_to)      $params[] = $date_to;
if ($company_id)   $params[] = $company_id;
if ($country_id)   $params[] = $country_id;
if ($region_id)    $params[] = $region_id;
if ($city_id)      $params[] = $city_id;
if ($city_zone_id) $params[] = $city_zone_id;
if ($area_id)      $params[] = $area_id;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── PHP grouping functions ────────────────────────────────────────────────────

function groupBySalesmanRows($rows) {
    $map = [];
    foreach ($rows as $row) {
        $key = $row['salesman_name'] ?? 'Unassigned';
        if (!isset($map[$key])) {
            $map[$key] = [
                'salesman_name' => $key,
                'rows'          => [],
                'total_balance' => 0.0
            ];
        }
        $map[$key]['rows'][] = $row;
        $map[$key]['total_balance'] += floatval($row['current_balance']);
    }
    return array_values($map);
}

function groupBySalesmanAndCustomerAndSimulate($rows) {
    $salesmanMap = [];
    foreach ($rows as $row) {
        $sKey = $row['salesman_name'] ?? 'Unassigned';
        if (!isset($salesmanMap[$sKey])) {
            $salesmanMap[$sKey] = [
                'salesman_name' => $sKey,
                'customers'     => []
            ];
        }
        $cKey = $row['customer_name'];
        if (!isset($salesmanMap[$sKey]['customers'][$cKey])) {
            $salesmanMap[$sKey]['customers'][$cKey] = [
                'customer_name'        => $row['customer_name'],
                'address'              => $row['address'],
                'primary_phone'        => $row['primary_phone'],
                'total_received'       => floatval($row['total_received']),
                'total_returns'        => floatval($row['total_returns']),
                'unallocated_received' => floatval($row['unallocated_received']),
                'bills'                => []
            ];
        }
        $salesmanMap[$sKey]['customers'][$cKey]['bills'][] = $row;
    }

    foreach ($salesmanMap as &$salesman) {
        $salesman['total_balance'] = 0.0;
        foreach ($salesman['customers'] as &$customer) {
            usort($customer['bills'], fn($a, $b) => strcmp($a['invoice_date'], $b['invoice_date']));
            $remaining     = $customer['total_received'];
            $total_balance = 0.0;
            foreach ($customer['bills'] as &$bill) {
                $balance        = floatval($bill['current_balance']);
                $total_balance += $balance;
                if ($remaining <= 0) {
                    $bill['sim_cleared'] = 0;
                    $bill['sim_balance'] = $balance;
                    $bill['sim_status']  = 'pending';
                } else {
                    $cleared             = min($remaining, $balance);
                    $remaining          -= $cleared;
                    $sim_balance         = $balance - $cleared;
                    $bill['sim_cleared'] = $cleared;
                    $bill['sim_balance'] = $sim_balance;
                    $bill['sim_status']  = $sim_balance <= 0 ? 'cleared' : ($cleared > 0 ? 'partial' : 'pending');
                }
            }
            $customer['total_balance'] = $total_balance;
            $rawNet = $total_balance - $customer['unallocated_received'];
            $customer['net_balance']   = $rawNet > 0 ? $rawNet : 0.0;
            $salesman['total_balance'] += $customer['net_balance'];
        }
        $salesman['customers'] = array_values($salesman['customers']);
    }
    return array_values($salesmanMap);
}

// ── Compute totals ────────────────────────────────────────────────────────────
$total_balance  = 0.0;
$total_received = 0.0;
$total_returns  = 0.0;
foreach ($rows as $row) {
    $total_balance  += $row['current_balance'];
    $total_received += $row['total_received'];
    $total_returns  += $row['total_returns'];
}

$territory_parts = array_filter([$country_name, $region_name, $city_name, $city_zone_name, $area_name]);
$territory_str   = implode(' > ', $territory_parts);

if ($view_mode === 'customer') {
    $salesmen_grouped = groupBySalesmanAndCustomerAndSimulate($rows);
} else {
    $salesmen_bill    = groupBySalesmanRows($rows);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Man Wise Recovery - Print</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
        }

        .print-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px;
        }

        .print-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .print-header img {
            max-height: 50px;
            max-width: 150px;
            margin: 0 auto 4px;
            display: block;
        }
        .print-header .company-name { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        .print-header .company-sub  { font-size: 9px; color: #444; margin-bottom: 1px; }
        .print-header .report-title { font-size: 13px; font-weight: bold; margin-top: 5px; }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 16px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 5px 10px;
            margin-bottom: 8px;
            font-size: 9px;
        }
        .filter-bar span { color: #555; }
        .filter-bar strong { color: #000; }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-bottom: 10px;
        }
        .summary-box {
            border: 1px solid #ddd;
            border-left: 3px solid #1f7bff;
            padding: 5px 10px;
            text-align: center;
        }
        .summary-box.green  { border-left-color: #2fbf71; }
        .summary-box.orange { border-left-color: #e8a23f; }
        .summary-box .s-label { font-size: 9px; color: #666; margin-bottom: 2px; }
        .summary-box .s-value { font-size: 12px; font-weight: bold; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        th {
            background: #f0f0f0;
            border: 1px solid #bbb;
            padding: 5px 4px;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
        }
        td {
            border: 1px solid #ccc;
            padding: 4px;
            font-size: 11px;
            vertical-align: middle;
        }
        tr:nth-child(even) td { background: #fafafa; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }
        .signature-cell { height: 24px; }

        /* Salesman group row */
        .salesman-header-row td {
            background: #e8f0fe;
            font-weight: bold;
            border-top: 2px solid #1f7bff;
            border-bottom: 1px solid #c5d9ff;
            font-size: 10px;
            padding: 6px 8px;
        }

        /* Subtotal row at end of each salesman section */
        .salesman-subtotal-row td {
            background: #f5f8ff;
            font-weight: bold;
            border-top: 1px solid #c5d9ff;
            font-size: 10px;
            text-align: right;
            padding: 4px 8px;
        }

        .print-footer {
            border-top: 2px solid #000;
            padding-top: 6px;
            margin-top: 6px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 14px;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
        }
        .sig-box { width: 30%; text-align: center; }
        .sig-line { border-bottom: 1px solid #000; height: 24px; margin-bottom: 3px; }
        .sig-label { font-size: 9px; color: #444; }

        .generated-by {
            text-align: center;
            font-size: 8px;
            color: #888;
            margin-top: 12px;
        }

        .view-badge {
            display: inline-block;
            margin-left: 8px;
            font-size: 9px;
            background: #1f7bff;
            color: #fff;
            padding: 2px 7px;
            border-radius: 10px;
            vertical-align: middle;
        }

        .no-data { text-align: center; padding: 30px; color: #888; font-size: 13px; }

        .no-print {
            position: fixed;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 8px;
            z-index: 999;
        }
        .btn-print {
            padding: 9px 18px;
            background: #1f7bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
        }
        .btn-close {
            padding: 9px 18px;
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
        }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .print-container { padding: 8px; }
            @page { size: A4 landscape; margin: 10mm; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128438; Print</button>
    <button class="btn-close" onclick="window.close()">&#10005; Close</button>
</div>

<div class="print-container">

    <!-- Header -->
    <div class="print-header">
        <?php if ($company_logo): ?>
            <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company_logo); ?>" alt="Logo">
        <?php endif; ?>
        <div class="company-name"><?php echo htmlspecialchars($company_name); ?></div>
        <?php if ($company_address): ?>
            <div class="company-sub"><?php echo htmlspecialchars($company_address); ?></div>
        <?php endif; ?>
        <?php if ($company_phone || $company_email): ?>
            <div class="company-sub">
                <?php if ($company_phone): ?>Phone: <?php echo htmlspecialchars($company_phone); ?><?php endif; ?>
                <?php if ($company_phone && $company_email): ?> &nbsp;|&nbsp; <?php endif; ?>
                <?php if ($company_email): ?>Email: <?php echo htmlspecialchars($company_email); ?><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="report-title">
            Sales Man Wise Recovery Report
            <?php if ($view_mode === 'customer'): ?>
                <span class="view-badge">Customer View</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
        <div>
            <strong>Date:</strong>
            <span>
                <?php echo $date_from ? date('d M Y', strtotime($date_from)) : 'All'; ?>
                &nbsp;&ndash;&nbsp;
                <?php echo $date_to   ? date('d M Y', strtotime($date_to))   : 'All'; ?>
            </span>
        </div>
        <?php if ($salesman_name): ?>
        <div><strong>Sales Man:</strong> <span><?php echo htmlspecialchars($salesman_name); ?></span></div>
        <?php endif; ?>
        <?php if ($territory_str): ?>
        <div><strong>Territory:</strong> <span><?php echo htmlspecialchars($territory_str); ?></span></div>
        <?php endif; ?>
        <div><strong>Generated By:</strong> <span><?php echo htmlspecialchars($user_name); ?></span></div>
        <div><strong>Generated On:</strong> <span><?php echo date('d M Y, h:i A'); ?></span></div>
    </div>

    <!-- Summary -->
    <div class="summary-row">
        <div class="summary-box">
            <div class="s-label">Total Balance</div>
            <div class="s-value"><?php echo $currency_symbol . number_format($total_balance, 2); ?></div>
        </div>
        <div class="summary-box green">
            <div class="s-label">Total Received</div>
            <div class="s-value"><?php echo $currency_symbol . number_format($total_received, 2); ?></div>
        </div>
        <div class="summary-box orange">
            <div class="s-label">Total Returns</div>
            <div class="s-value"><?php echo $currency_symbol . number_format($total_returns, 2); ?></div>
        </div>
    </div>

    <!-- Table -->
    <?php if (empty($rows)): ?>
        <div class="no-data">No records found for the selected filters.</div>

    <?php elseif ($view_mode === 'customer'): ?>
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width:2%">S#</th>
                <th style="width:18%">Customer Name</th>
                <th style="width:16%">Address</th>
                <th class="text-right" style="width:10%">Balance</th>
                <th style="width:10%">Received Amount</th>
                <th style="width:10%">Sales Return</th>
                <th class="text-center" style="width:8%">Days Overdue</th>
                <th style="width:12%">Signature</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $cGlobal = 0;
        foreach ($salesmen_grouped as $salesman):
            $sBalText = $currency_symbol . number_format($salesman['total_balance'], 2);
        ?>
            <tr class="salesman-header-row">
                <td colspan="8">
                    &#128100; <strong><?php echo htmlspecialchars($salesman['salesman_name']); ?></strong>
                    &nbsp;&mdash;&nbsp;
                    <?php echo count($salesman['customers']); ?> customer(s) &nbsp;&bull;&nbsp; Balance: <?php echo $sBalText; ?>
                </td>
            </tr>
            <?php foreach ($salesman['customers'] as $customer):
                $cGlobal++;
                $net_bal  = $customer['net_balance'];
                $balClass = $net_bal < 0 ? 'color:#c00;' : '';
                $balText  = ($net_bal < 0 ? '-' : '') . $currency_symbol . number_format(abs($net_bal), 2);
                $oldestDays = $customer['bills'][0]['days_overdue'] ?? '';
            ?>
            <tr>
                <td class="text-center"><?php echo $cGlobal; ?></td>
                <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                <td><?php echo htmlspecialchars($customer['address'] ?? ''); ?></td>
                <td class="text-right" style="<?php echo $balClass; ?>"><?php echo $balText; ?></td>
                <td></td>
                <td></td>
                <td class="text-center"><?php echo $oldestDays; ?></td>
                <td><div class="signature-cell"></div></td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="print-footer">
        <div class="totals-row">
            <span>Total Sales Men: <?php echo count($salesmen_grouped); ?></span>
            <span>Total Customers: <?php echo $cGlobal; ?></span>
            <span>Net Balance: <?php echo $currency_symbol . number_format($total_balance, 2); ?></span>
            <span>Total Received: <?php echo $currency_symbol . number_format($total_received, 2); ?></span>
            <span>Total Returns: <?php echo $currency_symbol . number_format($total_returns, 2); ?></span>
        </div>
        <div class="signatures">
            <div class="sig-box"><div class="sig-line"></div><div class="sig-label">Sales Manager</div></div>
            <div class="sig-box"><div class="sig-line"></div><div class="sig-label">Supervisor</div></div>
            <div class="sig-box"><div class="sig-line"></div><div class="sig-label">Accounts Officer</div></div>
        </div>
    </div>

    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th class="text-center" style="width:1%">S#</th>
                <th style="width:6%">Bill No</th>
                <th style="width:5%">Date</th>
                <th style="width:12%">Customer Name</th>
                <th style="width:12%">Address</th>
                <th style="width:6%">Phone</th>
                <th class="text-right" style="width:6%">Balance</th>
                <th class="text-center" style="width:5%">Received</th>
                <th class="text-center" style="width:5%">Return</th>
                <th class="text-center" style="width:4%">Days Overdue</th>
                <th style="width:8%">Signature</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $bGlobal = 0;
        foreach ($salesmen_bill as $salesman):
            $sBalText = $currency_symbol . number_format($salesman['total_balance'], 2);
        ?>
            <tr class="salesman-header-row">
                <td colspan="11">
                    &#128100; <strong><?php echo htmlspecialchars($salesman['salesman_name']); ?></strong>
                    &nbsp;&mdash;&nbsp;
                    <?php echo count($salesman['rows']); ?> bill(s) &nbsp;&bull;&nbsp; Balance: <?php echo $sBalText; ?>
                </td>
            </tr>
            <?php foreach ($salesman['rows'] as $row):
                $bGlobal++;
                $bal = floatval($row['current_balance']);
            ?>
            <tr>
                <td class="text-center"><?php echo $bGlobal; ?></td>
                <td><?php echo htmlspecialchars($row['bill_no'] ?? ''); ?></td>
                <td><?php echo !empty($row['invoice_date']) ? date('d-m-y', strtotime($row['invoice_date'])) : ''; ?></td>
                <td><?php echo htmlspecialchars($row['customer_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['primary_phone'] ?? ''); ?></td>
                <td class="text-right"><?php echo ($bal < 0 ? '-' : '') . $currency_symbol . number_format(abs($bal), 2); ?></td>
                <td></td>
                <td></td>
                <td class="text-center"><?php echo isset($row['days_overdue']) ? (int)$row['days_overdue'] : ''; ?></td>
                <td><div class="signature-cell"></div></td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="print-footer">
        <div class="totals-row">
            <span>Total Sales Men: <?php echo count($salesmen_bill); ?></span>
            <span>Total Records: <?php echo count($rows); ?></span>
            <span>Total Balance: <?php echo $currency_symbol . number_format($total_balance, 2); ?></span>
            <span>Total Received: <?php echo $currency_symbol . number_format($total_received, 2); ?></span>
            <span>Total Returns: <?php echo $currency_symbol . number_format($total_returns, 2); ?></span>
        </div>
        <div class="signatures">
            <div class="sig-box"><div class="sig-line"></div><div class="sig-label">Sales Manager</div></div>
            <div class="sig-box"><div class="sig-line"></div><div class="sig-label">Supervisor</div></div>
            <div class="sig-box"><div class="sig-line"></div><div class="sig-label">Accounts Officer</div></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="generated-by">
        LedgerOne ERP &bull; Sales Man Wise Recovery Report &bull; This is a computer-generated document
    </div>

</div>
</body>
</html>
