<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function getIntArrayParam($arrayName, $singleName = null) {
    $values = [];
    if (isset($_GET[$arrayName])) {
        $values = array_filter(array_map('intval', (array)$_GET[$arrayName]));
    } elseif ($singleName && !empty($_GET[$singleName])) {
        $values = [intval($_GET[$singleName])];
    }
    return array_values(array_unique($values));
}

// Extracts a short comparable code from a unit name like "Carton (Ctn)" -> "CTN",
// "Piece (Pc)" -> "PC". Falls back to the whole (trimmed, uppercased) name if there's
// no parenthetical short form, so plain "CTN" / "PCS" values still match.
function getUnitCode($unitName) {
    if (!$unitName) return '';
    if (preg_match('/\(([^)]+)\)/', $unitName, $m)) {
        return strtoupper(trim($m[1]));
    }
    return strtoupper(trim($unitName));
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

// Resolves the correct piece-conversion-factor for a sale-invoice-item line, in priority order:
// 1) product_uom_conversions (used when the product's UOM Type is "Group")
// 2) products.product_conversion_factor (used when the product's UOM Type is "Default Unit"
//    and the selected unit is a per-product-scope unit, e.g. Carton, with a manually entered factor)
// 3) uom.conversion_factor (universal-scope units, e.g. Dozen = 12)
// 4) 1 (no conversion info found - qty is already in base pieces)
function resolveConversionFactor($item) {
    if ($item['product_conversion_factor'] !== null) {
        return (float)$item['product_conversion_factor'];
    }
    if ($item['p_conv_factor'] !== null) {
        return (float)$item['p_conv_factor'];
    }
    if ($item['conversion_factor'] !== null) {
        return (float)$item['conversion_factor'];
    }
    return 1.0;
}

try {
    $report_type = $_GET['report_type'] ?? 'item-wise';
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

    if ($report_type === 'item-wise') {
        // Item-wise report query - get all unit variations
        $sql = "SELECT 
                    p.id,
                    p.name as product,
                    p.parent_product_id,
                    p.product_conversion_factor as p_conv_factor,
                    u.uom_name as unit,
                    u.conversion_factor as conversion_factor,
                    puc.conversion_factor as product_conversion_factor,
                    sii.quantity as qty,
                    sii.piece as piece_qty,
                    sii.carton as carton_qty,
                    sii.dozen as dozen_qty,
                    sii.foc_quantity as foc_qty,
                    sii.sale_price as rate,
                    sii.net_amount as amount,
                    sii.discount_percent as discount_percent,
                    sii.discount_amount as discount_amount,
                    sii.trade_offer_percent as trade_offer_percent,
                    sii.trade_offer_amount as trade_offer_amount,
                    sii.tax_amount as tax_amt,
                    CASE WHEN sii.parent_row_id IS NOT NULL THEN 1 ELSE 0 END as is_child,
                    si.sale_officer_id,
                    COALESCE(e.full_name, 'Unknown') as sales_officer_name
                FROM sale_invoice_items sii
                JOIN sale_invoice si ON sii.sale_invoice_id = si.id
                JOIN products p ON sii.product_id = p.id
                LEFT JOIN uom u ON sii.uom_id = u.id
                LEFT JOIN product_uom_conversions puc ON puc.product_id = p.id AND puc.uom_id = sii.uom_id
                LEFT JOIN customers c ON si.customer_id = c.id
                LEFT JOIN employees e ON si.sale_officer_id = e.id
                WHERE si.tenant_id = ? AND si.status = 'Posted'";
        
        $params = [$tenant_id];
        
        if ($date_from) {
            $sql .= " AND si.sale_date >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $sql .= " AND si.sale_date <= ?";
            $params[] = $date_to;
        }
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
        $grouped = [];
        foreach ($rawData as $item) {
            $key = $has_search_filters ? $item['sale_officer_id'] . '_' . $item['id'] : $item['id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => $item['id'],
                    'product' => $item['product'],
                    'parent_product_id' => $item['parent_product_id'],
                    'is_child' => $item['is_child'],
                    // Packing - the carton pack size for this product (e.g. 1 Ctn = 24 pcs),
                    // taken from the CTN unit line's effective conversion factor. Null if the
                    // product has no Carton unit line.
                    'ctn_pack_size' => null,
                    // PCS Total - computed AFTER the loop from the final ctn_qty/dzn_qty/pcs_qty/foc_qty
                    // totals below (ctn_qty*Packing + dzn_qty*12 + pcs_qty*1 + foc_qty*1), not
                    // accumulated per-row here. Guarantees it always matches the CTN/DZN/PCS/FOC columns.
                    'total_pack' => 0,
                    // CTN / PCS / DZN - summed directly from sale_invoice_items.carton / .piece / .dozen,
                    // the actual columns the POS invoice save writes to (see pos-add.php). Far more
                    // reliable than guessing from the unit name text.
                    'ctn_qty' => 0,
                    'pcs_qty' => 0,
                    'dzn_qty' => 0,
                    // FOC (Free of Charge) quantity - shown as its own column AND counted as
                    // 1 piece each toward total_pack (see the post-loop calc below).
                    'foc_qty' => 0,
                    'rate' => $item['rate'],
                    'amount' => 0,
                    'discount_amount' => 0,
                    'trade_offer_amount' => 0,
                    'tax_amt' => 0,
                    // Return Qty / Return Amt - not wired up yet, kept for future use.
                    // Net Amount (amount - r_amt + tax_amt) is computed on the frontend from these.
                    'r_qty' => 0,
                    'r_amt' => 0,
                    'units' => [],
                    'sales_officer_name' => $has_search_filters ? $item['sales_officer_name'] : 'All Sales Officers'
                ];
            }
            
            $unitName = $item['unit'] ?? '-';
            $convFactor = resolveConversionFactor($item);
            if (getUnitCode($unitName) === 'CTN') {
                $grouped[$key]['ctn_pack_size'] = $convFactor;
            }

            // Check if this unit already exists for this product
            $unitExists = false;
            foreach ($grouped[$key]['units'] as &$existingUnit) {
                if ($existingUnit['unit'] === $unitName) {
                    $existingUnit['qty'] += $item['qty'];
                    $unitExists = true;
                    break;
                }
            }
            
            // If unit doesn't exist, add it
            if (!$unitExists) {
                $grouped[$key]['units'][] = [
                    'unit' => $unitName,
                    'qty' => $item['qty']
                ];
            }
            
            $grouped[$key]['ctn_qty'] += (float)($item['carton_qty'] ?? 0);
            $grouped[$key]['pcs_qty'] += (float)($item['piece_qty'] ?? 0);
            $grouped[$key]['dzn_qty'] += (float)($item['dozen_qty'] ?? 0);
            $grouped[$key]['foc_qty'] += $item['foc_qty'];
            $grouped[$key]['amount'] += $item['amount'];
            $grouped[$key]['discount_amount'] += $item['discount_amount'];
            $grouped[$key]['trade_offer_amount'] += $item['trade_offer_amount'];
            $grouped[$key]['tax_amt'] += $item['tax_amt'];
        }

        // PCS Total = (CTN qty x Packing) + (DZN qty x 12) + (PCS qty x 1) + (FOC qty x 1).
        // Computed here from the final per-product totals so it always matches
        // the CTN/DZN/PCS/FOC columns shown in the table.
        foreach ($grouped as &$g) {
            $packing = $g['ctn_pack_size'] !== null ? $g['ctn_pack_size'] : 0;
            $g['total_pack'] = ($g['ctn_qty'] * $packing) + ($g['dzn_qty'] * 12) + ($g['pcs_qty'] * 1) + ($g['foc_qty'] * 1);
        }
        unset($g);
        
        $data = array_values($grouped);
        
        // Add serial numbers
        $data = array_map(function($item, $index) {
            return array_merge(['sNo' => $index + 1, 'isChild' => (bool)$item['is_child']], $item);
        }, $data, array_keys($data));
        
        echo json_encode(['success' => true, 'data' => $data]);
        
    } else {
        // Bill-wise report query
        $sql = "SELECT 
                    si.id,
                    si.bill_no as invoiceNo,
                    c.customer_code as custCode,
                    c.customer_name as custName,
                    c.address,
                    si.total_bill as billAmount,
                    0 as returnAmount,
                    si.net_amount as netAmount,
                    COALESCE(e.full_name, 'Unknown') as sales_officer_name
                FROM sale_invoice si
                JOIN customers c ON si.customer_id = c.id
                LEFT JOIN employees e ON si.sale_officer_id = e.id
                WHERE si.tenant_id = ? AND si.status = 'Posted'";
        
        $params = [$tenant_id];
        
        if ($date_from) {
            $sql .= " AND si.sale_date >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $sql .= " AND si.sale_date <= ?";
            $params[] = $date_to;
        }
        addInFilter($sql, $params, 'si.sale_officer_id', $sales_officer_ids);
        addInFilter($sql, $params, 'si.supplier_man_id', $supplier_man_ids);
        addBillVendorFilter($sql, $params, $vendor_ids);
        addInFilter($sql, $params, 'si.company_id', $company_ids);
        addInvoiceTypeFilter($sql, $params, $invoice_types);
        addInFilter($sql, $params, 'c.city_id', $city_ids);
        addInFilter($sql, $params, 'c.city_zone_id', $city_zone_ids);
        addInFilter($sql, $params, 'c.area_id', $area_ids);
        
        $sql .= " ORDER BY si.bill_no DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add serial numbers
        $data = array_map(function($item, $index) {
            return array_merge(['sNo' => $index + 1], $item);
        }, $data, array_keys($data));
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}