<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';

// Get currency symbol
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$tenant_id]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];

// Get tenant info
$stmt = $pdo->prepare("SELECT company_name, address, phone, email FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

function getIntArrayParam($arrayName, $singleName = null) {
    $values = [];
    if (isset($_GET[$arrayName])) {
        $values = array_filter(array_map('intval', (array)$_GET[$arrayName]));
    } elseif ($singleName && !empty($_GET[$singleName])) {
        $values = [intval($_GET[$singleName])];
    }
    return array_values(array_unique($values));
}

function getStringArrayParam($arrayName, $singleName = null) {
    $values = [];
    if (isset($_GET[$arrayName])) {
        $values = array_filter(array_map('trim', (array)$_GET[$arrayName]));
    } elseif ($singleName && !empty($_GET[$singleName])) {
        $values = [trim($_GET[$singleName])];
    }
    return array_values(array_unique($values));
}

function getNamesByIds($pdo, $table, $nameColumn, $ids, $tenantId) {
    if (empty($ids)) return 'All';
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, $nameColumn FROM $table WHERE id IN ($placeholders) AND tenant_id = ?");
    $stmt->execute(array_merge($ids, [$tenantId]));
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return !empty($rows) ? implode(', ', array_values($rows)) : 'All';
}

function addInFilter(&$sql, &$params, $column, $values) {
    if (empty($values)) return;
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $sql .= " AND $column IN ($placeholders)";
    $params = array_merge($params, $values);
}

function addInvoiceTypeFilter(&$sql, &$params, $invoiceTypes) {
    if (empty($invoiceTypes)) return;

    $normalTypes = array_values(array_diff($invoiceTypes, ['OutStation']));
    $conditions = [];

    if (in_array('OutStation', $invoiceTypes, true)) {
        $conditions[] = "c.is_out_station = 1";
    }

    if (!empty($normalTypes)) {
        $placeholders = implode(',', array_fill(0, count($normalTypes), '?'));
        $conditions[] = "si.invoice_type IN ($placeholders)";
        $params = array_merge($params, $normalTypes);
    }

    if (!empty($conditions)) {
        $sql .= " AND (" . implode(' OR ', $conditions) . ")";
    }
}

function addBillVendorFilter(&$sql, &$params, $vendorIds) {
    if (empty($vendorIds)) return;
    $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));
    $sql .= " AND EXISTS (
        SELECT 1
        FROM sale_invoice_items sii_vendor
        JOIN products p_vendor ON sii_vendor.product_id = p_vendor.id
        WHERE sii_vendor.sale_invoice_id = si.id
          AND p_vendor.vendor_id IN ($placeholders)
    )";
    $params = array_merge($params, $vendorIds);
}

// Get report parameters
$report_type = $_GET['report_type'] ?? 'item-wise';
$reference = $_GET['reference'] ?? null;
$invoice_types = getStringArrayParam('invoice_types', 'invoice_type');
$sales_officer_ids = getIntArrayParam('sales_officer_ids', 'sales_officer_id');
$supplier_man_ids = getIntArrayParam('supplier_man_ids', 'supplier_man_id');
$vendor_ids = getIntArrayParam('vendor_ids', 'vendor_id');
$company_ids = getIntArrayParam('company_ids', 'company_id');
$city_ids = getIntArrayParam('city_ids', 'city_id');
$city_zone_ids = getIntArrayParam('city_zone_ids', 'city_zone_id');
$area_ids = getIntArrayParam('area_ids', 'area_id');
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;
$has_search_filters = !empty($invoice_types)
    || !empty($sales_officer_ids)
    || !empty($supplier_man_ids)
    || !empty($vendor_ids)
    || !empty($company_ids)
    || !empty($city_ids)
    || !empty($city_zone_ids)
    || !empty($area_ids);

$invoice_type_names = empty($invoice_types)
    ? 'All'
    : implode(', ', array_map(function($type) { return $type === 'OutStation' ? 'Out Station' : $type; }, $invoice_types));
$sales_officer_names = getNamesByIds($pdo, 'employees', 'full_name', $sales_officer_ids, $tenant_id);
$supplier_man_name = getNamesByIds($pdo, 'employees', 'full_name', $supplier_man_ids, $tenant_id);
$vendor_name = getNamesByIds($pdo, 'suppliers', 'supplier_name', $vendor_ids, $tenant_id);
$company_name = getNamesByIds($pdo, 'companies', 'company_name', $company_ids, $tenant_id);
$city_name = getNamesByIds($pdo, 'cities', 'city_name', $city_ids, $tenant_id);
$city_zone_name = getNamesByIds($pdo, 'city_zones', 'city_zone_name', $city_zone_ids, $tenant_id);
$area_name = getNamesByIds($pdo, 'areas', 'area_name', $area_ids, $tenant_id);

// Fetch report data
if ($report_type === 'item-wise') {
    $sql = "SELECT 
                p.id,
                p.name as product,
                p.parent_product_id,
                u.uom_name as unit,
                sii.quantity as qty,
                sii.foc_quantity as foc_qty,
                sii.sale_price as rate,
                sii.net_amount as amount,
                CASE WHEN sii.parent_row_id IS NOT NULL THEN 1 ELSE 0 END as is_child,
                si.sale_officer_id,
                COALESCE(e.full_name, 'Unknown') as sales_officer_name
            FROM sale_invoice_items sii
            JOIN sale_invoice si ON sii.sale_invoice_id = si.id
            JOIN products p ON sii.product_id = p.id
            LEFT JOIN uom u ON sii.uom_id = u.id
            LEFT JOIN customers c ON si.customer_id = c.id
            LEFT JOIN employees e ON si.sale_officer_id = e.id
            WHERE si.tenant_id = ? AND si.status = 'Posted'";
    
    $params = [$tenant_id];
    
    if ($date_from) { $sql .= " AND si.sale_date >= ?"; $params[] = $date_from; }
    if ($date_to)   { $sql .= " AND si.sale_date <= ?"; $params[] = $date_to; }
    addInFilter($sql, $params, 'si.sale_officer_id', $sales_officer_ids);
    addInFilter($sql, $params, 'si.supplier_man_id', $supplier_man_ids);
    addInFilter($sql, $params, 'p.vendor_id', $vendor_ids);
    addInFilter($sql, $params, 'si.company_id', $company_ids);
    addInvoiceTypeFilter($sql, $params, $invoice_types);
    addInFilter($sql, $params, 'c.city_id', $city_ids);
    addInFilter($sql, $params, 'c.city_zone_id', $city_zone_ids);
    addInFilter($sql, $params, 'c.area_id', $area_ids);
    
    $sql .= $has_search_filters
        ? " ORDER BY si.sale_officer_id, p.id, u.uom_name"
        : " ORDER BY p.id, u.uom_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // With no filters, show one row per product across all invoices/officers.
    // Once filters are applied, preserve the officer-aware grouping used by DSR.
    $byOfficer = [];
    foreach ($rawData as $item) {
        $officerName = $has_search_filters ? $item['sales_officer_name'] : 'All Sales Officers';
        $key = $has_search_filters ? $item['sale_officer_id'] . '_' . $item['id'] : $item['id'];
        if (!isset($byOfficer[$officerName])) $byOfficer[$officerName] = [];
        if (!isset($byOfficer[$officerName][$key])) {
            $byOfficer[$officerName][$key] = [
                'product' => $item['product'],
                'is_child' => $item['is_child'],
                'foc_qty' => 0,
                'rate' => $item['rate'],
                'amount' => 0,
                'units' => []
            ];
        }
        $unitName = $item['unit'] ?? '-';
        $unitExists = false;
        foreach ($byOfficer[$officerName][$key]['units'] as &$eu) {
            if ($eu['unit'] === $unitName) { $eu['qty'] += $item['qty']; $unitExists = true; break; }
        }
        if (!$unitExists) $byOfficer[$officerName][$key]['units'][] = ['unit' => $unitName, 'qty' => $item['qty']];
        $byOfficer[$officerName][$key]['foc_qty'] += $item['foc_qty'];
        $byOfficer[$officerName][$key]['amount']  += $item['amount'];
    }
    $multiOfficer = !empty($sales_officer_ids) && count($byOfficer) > 1;
} else {
    $sql = "SELECT 
                si.bill_no as invoiceNo,
                c.customer_code as custCode,
                c.customer_name as custName,
                c.address,
                si.total_bill as billAmount,
                0 as returnAmount,
                si.net_amount as netAmount,
                COALESCE(e.full_name, 'Unknown') as sales_officer_name,
                si.sale_officer_id
            FROM sale_invoice si
            JOIN customers c ON si.customer_id = c.id
            LEFT JOIN employees e ON si.sale_officer_id = e.id
            WHERE si.tenant_id = ? AND si.status = 'Posted'";
    
    $params = [$tenant_id];
    
    if ($date_from) { $sql .= " AND si.sale_date >= ?"; $params[] = $date_from; }
    if ($date_to)   { $sql .= " AND si.sale_date <= ?"; $params[] = $date_to; }
    addInFilter($sql, $params, 'si.sale_officer_id', $sales_officer_ids);
    addInFilter($sql, $params, 'si.supplier_man_id', $supplier_man_ids);
    addBillVendorFilter($sql, $params, $vendor_ids);
    addInFilter($sql, $params, 'si.company_id', $company_ids);
    addInvoiceTypeFilter($sql, $params, $invoice_types);
    addInFilter($sql, $params, 'c.city_id', $city_ids);
    addInFilter($sql, $params, 'c.city_zone_id', $city_zone_ids);
    addInFilter($sql, $params, 'c.area_id', $area_ids);
    
    $sql .= " ORDER BY si.sale_officer_id, si.bill_no DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawBills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by officer
    $byOfficerBill = [];
    foreach ($rawBills as $row) {
        $name = $row['sales_officer_name'];
        if (!isset($byOfficerBill[$name])) $byOfficerBill[$name] = [];
        $byOfficerBill[$name][] = $row;
    }
    $multiOfficerBill = !empty($sales_officer_ids) && count($byOfficerBill) > 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Sales Report - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            color: #000;
        }
        
        .print-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 12px;
            color: #333;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0 10px;
            text-align: center;
        }
        
        .report-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .summary {
            margin-top: 20px;
            text-align: right;
        }
        
        .summary-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .summary-label {
            width: 150px;
            font-weight: bold;
        }
        
        .summary-value {
            width: 120px;
            text-align: right;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #000;
            font-size: 11px;
            text-align: center;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #1f7bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-btn:hover {
            background-color: #1a6cdc;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print Report</button>
    
    <div class="print-container">
        <div class="header">
            <div class="company-name"><?php echo htmlspecialchars($tenant['company_name'] ?? 'Company Name'); ?></div>
            <div class="company-details">
                <?php echo htmlspecialchars($tenant['address'] ?? ''); ?><br>
                Phone: <?php echo htmlspecialchars($tenant['phone'] ?? ''); ?> | Email: <?php echo htmlspecialchars($tenant['email'] ?? ''); ?>
            </div>
        </div>
        
        <div class="report-title">
            <?php echo $report_type === 'item-wise' ? 'Item-wise Daily Sales Report' : 'Bill-wise Daily Sales Report'; ?>
        </div>
        
        <div class="report-info">
            <div>
                <strong>Reference #:</strong> <?php echo htmlspecialchars($reference ?? 'N/A'); ?><br>
                <strong>Type:</strong> <?php echo htmlspecialchars($invoice_type_names); ?><br>
                <strong>Sales Officer:</strong> <?php echo htmlspecialchars($sales_officer_names); ?><br>
                <strong>Supplier Man:</strong> <?php echo htmlspecialchars($supplier_man_name); ?><br>
                <strong>Vendor:</strong> <?php echo htmlspecialchars($vendor_name); ?><br>
                <strong>Company:</strong> <?php echo htmlspecialchars($company_name); ?><br>
                <strong>City:</strong> <?php echo htmlspecialchars($city_name); ?><br>
                <strong>City Zone:</strong> <?php echo htmlspecialchars($city_zone_name); ?><br>
                <strong>Area:</strong> <?php echo htmlspecialchars($area_name); ?>
            </div>
            <div>
                <strong>Date Range:</strong> <?php echo $date_from ? date('d-M-Y', strtotime($date_from)) : 'N/A'; ?> to <?php echo $date_to ? date('d-M-Y', strtotime($date_to)) : 'N/A'; ?>
            </div>
            <div>
                <strong>Print Date:</strong> <?php echo date('d-M-Y h:i A'); ?>
            </div>
        </div>
        
        <?php if ($report_type === 'item-wise'): ?>
            <table>
                <thead>
                    <tr>
                        <th class="text-center">S#</th>
                        <th>Product</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">FOC Qty</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalAmount = 0;
                    $sNo = 0;
                    foreach ($byOfficer as $officerName => $items):
                        if ($multiOfficer): ?>
                        <tr>
                            <td colspan="6" style="background:#f0f0f0; font-weight:bold; padding:6px 8px;"><?php echo htmlspecialchars($officerName); ?></td>
                        </tr>
                        <?php endif;
                        foreach ($items as $item):
                            $sNo++;
                            if (!$item['is_child']) $totalAmount += $item['amount'];
                            $indent = $item['is_child'] ? 'padding-left: 30px;' : '';
                            $arrow  = $item['is_child'] ? '&rarr; ' : '';
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $sNo; ?></td>
                            <td style="<?php echo $indent; ?>"><?php echo $arrow . htmlspecialchars($item['product']); ?></td>
                            <td class="text-right"><?php echo implode(', ', array_map(function($u) { return $u['unit'] . ' ' . intval($u['qty']); }, $item['units'])); ?></td>
                            <td class="text-right"><?php echo number_format($item['foc_qty'], 2); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($item['rate'], 2); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($item['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>
            
            <div class="summary">
                <div class="summary-row">
                    <div class="summary-label">Total Amount:</div>
                    <div class="summary-value"><?php echo $currency_symbol . number_format($totalAmount, 2); ?></div>
                </div>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th class="text-center">S#</th>
                        <th>Invoice No</th>
                        <th>Customer Code</th>
                        <th>Customer Name</th>
                        <th>Address</th>
                        <th class="text-right">Net Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalBillAmount = 0;
                    $totalReturnAmount = 0;
                    $totalNetAmount = 0;
                    $sNoBill = 0;
                    foreach ($byOfficerBill as $officerName => $bills):
                        if ($multiOfficerBill): ?>
                        <tr>
                            <td colspan="6" style="background:#f0f0f0; font-weight:bold; padding:6px 8px;"><?php echo htmlspecialchars($officerName); ?></td>
                        </tr>
                        <?php endif;
                        foreach ($bills as $item):
                            $sNoBill++;
                            $totalBillAmount += $item['billAmount'];
                            $totalReturnAmount += $item['returnAmount'];
                            $totalNetAmount += $item['netAmount'];
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $sNoBill; ?></td>
                            <td><?php echo htmlspecialchars($item['invoiceNo']); ?></td>
                            <td><?php echo htmlspecialchars($item['custCode']); ?></td>
                            <td><?php echo htmlspecialchars($item['custName']); ?></td>
                            <td><?php echo htmlspecialchars($item['address'] ?? '-'); ?></td>
                            <td class="text-right"><?php echo $currency_symbol . number_format($item['netAmount'], 2); ?></td>
                        </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>
            
            <div class="summary">
                <div class="summary-row">
                    <div class="summary-label">Total Bills:</div>
                    <div class="summary-value"><?php echo $sNoBill; ?></div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Net Amount:</div>
                    <div class="summary-value"><?php echo $currency_symbol . number_format($totalNetAmount, 2); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            Generated by LedgerOne ERP | <?php echo date('d-M-Y h:i A'); ?>
        </div>
    </div>
</body>
</html>
