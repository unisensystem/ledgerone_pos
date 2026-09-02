<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $currency_sql = "SELECT c.symbol FROM tenant_currencies tc 
                     JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
                     WHERE tc.tenant_id = ? AND tc.is_base_currency = 1";
    $currency_stmt = $pdo->prepare($currency_sql);
    $currency_stmt->execute([$tenant_id]);
    $currency = $currency_stmt->fetch(PDO::FETCH_ASSOC);
    $currency_symbol = $currency['symbol'] ?? '$';

    $action = $_GET['action'] ?? 'position';

    switch ($action) {
        case 'position':
            $branch_id = $_GET['branch_id'] ?? null;
            $product_id = $_GET['product_id'] ?? null;
            $inventory_type_id = $_GET['inventory_type_id'] ?? null;
            $vendor_id = $_GET['vendor_id'] ?? null;
            $stock_status = $_GET['stock_status'] ?? null;
            $status = $_GET['status'] ?? null;
            $from_date = $_GET['from_date'] ?? null;
            $to_date = $_GET['to_date'] ?? null;
            $valuation_method = $_GET['valuation_method'] ?? 'AVCO';
            $show_base_units = isset($_GET['show_base_units']) && $_GET['show_base_units'] === 'true';

            // Debug log
            error_log('=== Stock Position Debug ===');
            error_log('show_base_units parameter: ' . ($_GET['show_base_units'] ?? 'NOT SET'));
            error_log('show_base_units boolean: ' . ($show_base_units ? 'TRUE' : 'FALSE'));
            error_log('branch_id: ' . ($branch_id ?? 'NOT SET'));
            error_log('product_id: ' . ($product_id ?? 'NOT SET'));

            $allowed_methods = ['AVCO', 'FIFO', 'LIFO', 'TRADE_PRICE'];
            if (!in_array($valuation_method, $allowed_methods, true)) {
                $valuation_method = 'AVCO';
            }

            error_log('Query path: ' . ($show_base_units ? 'BASE UNITS' : 'ORIGINAL UNITS'));

            // Unit conversion factor - only apply if unit has a base_unit_id or product-specific conversion
            $conversionFactor = "COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1)";

            // Unit cost calculation based on valuation method
            $unitCostCalc = $valuation_method === 'TRADE_PRICE'
                ? "COALESCE(p.trade_price, 0)"
                : "COALESCE(AVG(CASE WHEN sl.qty_in > 0 THEN sl.unit_cost ELSE NULL END), 0)";

            if ($show_base_units) {
                // Convert to base units and group by product/branch/account/base_unit
                $sql = "SELECT
                            p.id as product_id,
                            p.name as product_name,
                            p.parent_product_id,
                            pp.name as parent_product_name,
                            COALESCE(b.branch_name, 'No Branch') as branch_name,
                            COALESCE(b.branch_type, '') as branch_type,
                            pb.branch_name as parent_branch_name,
                            COALESCE(base_u.uom_name, 'L') as unit_symbol,
                            COALESCE(a.name, 'Trading Inventory') as account_name,
                            sl.branch_id,
                            sl.account_id,
                            COALESCE(u.base_unit_id, u.id) as unit_id,
                            p.min_stock_level,
                            p.max_stock_level,
                            COALESCE(SUM(CASE WHEN ? IS NULL OR sl.transaction_date < ? THEN
                                (sl.qty_in * {$conversionFactor}) - (sl.qty_out * {$conversionFactor})
                            ELSE 0 END), 0) as opening_balance,
                            COALESCE(SUM(CASE WHEN (? IS NULL OR sl.transaction_date >= ?) AND (? IS NULL OR sl.transaction_date <= ?) THEN
                                sl.qty_in * {$conversionFactor}
                            ELSE 0 END), 0) as total_qty_in,
                            COALESCE(SUM(CASE WHEN (? IS NULL OR sl.transaction_date >= ?) AND (? IS NULL OR sl.transaction_date <= ?) THEN
                                sl.qty_out * {$conversionFactor}
                            ELSE 0 END), 0) as total_qty_out,
                            COALESCE(SUM((sl.qty_in * {$conversionFactor}) - (sl.qty_out * {$conversionFactor})), 0) as current_stock,
                            {$unitCostCalc} as unit_cost
                        FROM stock_ledger sl
                        JOIN products p ON sl.product_id = p.id AND sl.tenant_id = p.tenant_id
                        LEFT JOIN products pp ON p.parent_product_id = pp.id
                        LEFT JOIN branches b ON sl.branch_id = b.id
                        LEFT JOIN branches pb ON b.parent_branch_id = pb.id
                        LEFT JOIN accounts a ON sl.account_id = a.id
                        LEFT JOIN uom u ON sl.unit_id = u.id
                        LEFT JOIN uom base_u ON COALESCE(u.base_unit_id, u.id) = base_u.id
                        LEFT JOIN product_uom_conversions puc ON p.id = puc.product_id AND sl.unit_id = puc.uom_id
                        LEFT JOIN purchase_invoice pi ON sl.reference_table = 'purchase_invoice' AND sl.reference_id = pi.id
                        LEFT JOIN sale_invoice si ON sl.reference_table = 'sale_invoice' AND sl.reference_id = si.id
                        WHERE sl.tenant_id = ? AND p.product_type = 'physical' AND sl.account_id IS NOT NULL";

                $params = [$from_date, $from_date, $from_date, $from_date, $to_date, $to_date, $from_date, $from_date, $to_date, $to_date, $tenant_id];

                if ($branch_id) {
                    $sql .= " AND sl.branch_id = ?";
                    $params[] = $branch_id;
                }
                if ($product_id) {
                    $sql .= " AND p.id = ?";
                    $params[] = $product_id;
                }
                if ($vendor_id) {
                    $sql .= " AND p.vendor_id = ?";
                    $params[] = $vendor_id;
                }
                if ($stock_status) {
                    $sql .= " AND sl.stock_status = ?";
                    $params[] = $stock_status;
                }
                if ($inventory_type_id) {
                    $sql .= " AND sl.account_id = ?";
                    $params[] = $inventory_type_id;
                }

                $sql .= " GROUP BY p.id, sl.branch_id, sl.account_id, COALESCE(u.base_unit_id, u.id) ORDER BY COALESCE(p.parent_product_id, p.id), p.parent_product_id IS NULL DESC, p.name";
            } else {
                // Show in original units with proportional distribution across unit types.
                // Deductions recorded in base units (e.g. FOC in Pieces) are distributed across all
                // unit types proportionally based on each unit's share of total incoming base units.
                // May produce fractional quantities (e.g. 1.5 Cartons) and negative values are allowed.
                $conversionFactor2 = "COALESCE(CASE WHEN u2.base_unit_id IS NOT NULL AND u2.unit_scope = 'universal' THEN u2.conversion_factor WHEN puc2.conversion_factor IS NOT NULL THEN puc2.conversion_factor ELSE 1 END, 1)";

                $filterSub = "";
                $filterAgg = "";
                $filterParamsSub = [];
                $filterParamsAgg = [];

                if ($branch_id) {
                    $filterSub .= " AND sl.branch_id = ?";
                    $filterAgg .= " AND sl2.branch_id = ?";
                    $filterParamsSub[] = $branch_id;
                    $filterParamsAgg[] = $branch_id;
                }
                if ($product_id) {
                    $filterSub .= " AND p.id = ?";
                    $filterAgg .= " AND p2.id = ?";
                    $filterParamsSub[] = $product_id;
                    $filterParamsAgg[] = $product_id;
                }
                if ($vendor_id) {
                    $filterSub .= " AND p.vendor_id = ?";
                    $filterAgg .= " AND p2.vendor_id = ?";
                    $filterParamsSub[] = $vendor_id;
                    $filterParamsAgg[] = $vendor_id;
                }
                if ($stock_status) {
                    $filterSub .= " AND sl.stock_status = ?";
                    $filterAgg .= " AND sl2.stock_status = ?";
                    $filterParamsSub[] = $stock_status;
                    $filterParamsAgg[] = $stock_status;
                }
                if ($inventory_type_id) {
                    $filterSub .= " AND sl.account_id = ?";
                    $filterAgg .= " AND sl2.account_id = ?";
                    $filterParamsSub[] = $inventory_type_id;
                    $filterParamsAgg[] = $inventory_type_id;
                }

                $sql = "SELECT
                            sub.product_id, sub.product_name, sub.parent_product_id, sub.parent_product_name,
                            sub.branch_name, sub.branch_type, sub.parent_branch_name, sub.unit_symbol,
                            sub.account_name, sub.branch_id, sub.account_id, sub.unit_id,
                            sub.min_stock_level, sub.max_stock_level,
                            sub.total_qty_in, sub.total_qty_out, sub.unit_cost,
                            COALESCE(
                                CASE WHEN sub.unit_cf <= 1 THEN
                                    ROUND((sub.unit_base_in_total / NULLIF(agg.total_base_in, 0)) * agg.total_base_opening)
                                ELSE
                                    (sub.unit_base_in_total / NULLIF(agg.total_base_in, 0)) * agg.total_base_opening / NULLIF(sub.unit_cf, 0)
                                END,
                            0) as opening_balance,
                            CASE
                                WHEN agg.total_base_in > 0 THEN
                                    CASE WHEN sub.unit_cf <= 1 THEN
                                        ROUND((sub.unit_base_in_total / NULLIF(agg.total_base_in, 0)) * agg.total_base_closing)
                                    ELSE
                                        (sub.unit_base_in_total / NULLIF(agg.total_base_in, 0)) * agg.total_base_closing / NULLIF(sub.unit_cf, 0)
                                    END
                                WHEN agg.total_base_out > 0 THEN
                                    CASE WHEN sub.unit_cf <= 1 THEN
                                        ROUND((sub.unit_base_out_total / NULLIF(agg.total_base_out, 0)) * agg.total_base_closing)
                                    ELSE
                                        (sub.unit_base_out_total / NULLIF(agg.total_base_out, 0)) * agg.total_base_closing / NULLIF(sub.unit_cf, 0)
                                    END
                                ELSE 0
                            END as current_stock
                        FROM (
                            SELECT
                                p.id as product_id,
                                p.name as product_name,
                                p.parent_product_id,
                                pp.name as parent_product_name,
                                COALESCE(b.branch_name, 'No Branch') as branch_name,
                                COALESCE(b.branch_type, '') as branch_type,
                                pb.branch_name as parent_branch_name,
                                COALESCE(u.uom_name, 'L') as unit_symbol,
                                COALESCE(a.name, 'Trading Inventory') as account_name,
                                sl.branch_id,
                                sl.account_id,
                                sl.unit_id,
                                p.min_stock_level,
                                p.max_stock_level,
                                COALESCE(SUM(sl.qty_in * {$conversionFactor}), 0) as unit_base_in_total,
                                COALESCE(SUM(sl.qty_out * {$conversionFactor}), 0) as unit_base_out_total,
                                COALESCE(AVG({$conversionFactor}), 1) as unit_cf,
                                COALESCE(SUM(CASE WHEN (? IS NULL OR sl.transaction_date >= ?) AND (? IS NULL OR sl.transaction_date <= ?) THEN sl.qty_in ELSE 0 END), 0) as total_qty_in,
                                COALESCE(SUM(CASE WHEN (? IS NULL OR sl.transaction_date >= ?) AND (? IS NULL OR sl.transaction_date <= ?) THEN sl.qty_out ELSE 0 END), 0) as total_qty_out,
                                {$unitCostCalc} as unit_cost
                            FROM stock_ledger sl
                            JOIN products p ON sl.product_id = p.id AND sl.tenant_id = p.tenant_id
                            LEFT JOIN products pp ON p.parent_product_id = pp.id
                            LEFT JOIN branches b ON sl.branch_id = b.id
                            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
                            LEFT JOIN accounts a ON sl.account_id = a.id
                            LEFT JOIN uom u ON sl.unit_id = u.id
                            LEFT JOIN product_uom_conversions puc ON p.id = puc.product_id AND sl.unit_id = puc.uom_id
                            LEFT JOIN purchase_invoice pi ON sl.reference_table = 'purchase_invoice' AND sl.reference_id = pi.id
                            LEFT JOIN sale_invoice si ON sl.reference_table = 'sale_invoice' AND sl.reference_id = si.id
                            WHERE sl.tenant_id = ? AND p.product_type = 'physical' AND sl.account_id IS NOT NULL{$filterSub}
                            GROUP BY p.id, sl.branch_id, sl.account_id, sl.unit_id
                        ) sub
                        JOIN (
                            SELECT
                                sl2.product_id, sl2.branch_id, sl2.account_id,
                                COALESCE(SUM(sl2.qty_in * {$conversionFactor2}), 0) as total_base_in,
                                COALESCE(SUM(sl2.qty_out * {$conversionFactor2}), 0) as total_base_out,
                                COALESCE(SUM((sl2.qty_in - sl2.qty_out) * {$conversionFactor2}), 0) as total_base_closing,
                                COALESCE(SUM(CASE WHEN ? IS NULL OR sl2.transaction_date < ? THEN
                                    (sl2.qty_in - sl2.qty_out) * {$conversionFactor2}
                                ELSE 0 END), 0) as total_base_opening
                            FROM stock_ledger sl2
                            JOIN products p2 ON sl2.product_id = p2.id AND sl2.tenant_id = p2.tenant_id
                            LEFT JOIN uom u2 ON sl2.unit_id = u2.id
                            LEFT JOIN product_uom_conversions puc2 ON p2.id = puc2.product_id AND sl2.unit_id = puc2.uom_id
                            WHERE sl2.tenant_id = ? AND p2.product_type = 'physical' AND sl2.account_id IS NOT NULL{$filterAgg}
                            GROUP BY sl2.product_id, sl2.branch_id, sl2.account_id
                        ) agg ON sub.product_id = agg.product_id AND sub.branch_id = agg.branch_id AND sub.account_id = agg.account_id
                        ORDER BY COALESCE(sub.parent_product_id, sub.product_id), sub.parent_product_id IS NULL DESC, sub.product_name";

                $params = [
                    // sub: total_qty_in period filter
                    $from_date,
                    $from_date,
                    $to_date,
                    $to_date,
                    // sub: total_qty_out period filter
                    $from_date,
                    $from_date,
                    $to_date,
                    $to_date,
                    // sub: WHERE tenant_id
                    $tenant_id,
                ];
                $params = array_merge($params, $filterParamsSub, [
                    // agg: opening balance date filter
                    $from_date,
                    $from_date,
                    // agg: WHERE tenant_id
                    $tenant_id,
                ], $filterParamsAgg);
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Debug info
            $debug = [
                'query' => $sql,
                'params' => $params,
                'count' => count($positions),
                'valuation_method' => $valuation_method,
                'first_record' => count($positions) > 0 ? $positions[0] : null
            ];

            foreach ($positions as &$position) {
                // Calculate stock value
                $position['stock_value'] = $position['current_stock'] * $position['unit_cost'];

                $stock = $position['current_stock'];
                $min_level = $position['min_stock_level'] ?? 0;
                $max_level = $position['max_stock_level'] ?? 0;

                $type_map = [
                    'head_office' => 'Head Office',
                    'petrol_station' => 'Petrol Station',
                    'convenience_store' => 'Convenience Store',
                    'car_wash' => 'Car Wash',
                    'fuel_tank' => 'Fuel Tank',
                    'warehouse' => 'Warehouse',
                    'distribution_center' => 'Distribution Center'
                ];
                $type_display = $type_map[$position['branch_type']] ?? $position['branch_type'];
                $branch_display = $position['branch_name'] . ' (' . $type_display . ')';
                if ($position['parent_branch_name']) {
                    $branch_display = $position['parent_branch_name'] . ' > ' . $branch_display;
                }
                $position['branch_display'] = $branch_display;
                $position['inventory_type'] = $position['account_name'];

                if ($stock <= 0) {
                    $position['status'] = 'out-of-stock';
                } elseif ($stock < $min_level) {
                    $position['status'] = 'low-stock';
                } elseif ($max_level > 0 && $stock > $max_level) {
                    $position['status'] = 'overstock';
                } else {
                    $position['status'] = 'in-stock';
                }
            }

            if ($status) {
                $positions = array_filter($positions, function ($pos) use ($status) {
                    return $pos['status'] === $status;
                });
                $positions = array_values($positions);
            }

            echo json_encode(['success' => true, 'data' => $positions, 'currency' => $currency_symbol, 'debug' => $debug]);
            break;

      case 'ledger':

    $product_id = $_GET['product_id'] ?? null;
    $company_id = $_GET['company_id'] ?? null;
    $vendor_id = $_GET['vendor_id'] ?? null;
    $customer_id = $_GET['customer_id'] ?? null;
    $from_date = $_GET['from_date'] ?? null;
    $to_date = $_GET['to_date'] ?? null;


    /*
    |--------------------------------------------------------------------------
    | BROUGHT FORWARD BALANCE
    |--------------------------------------------------------------------------
    |
    | If from_date is selected, calculate stock before from_date.
    | Balance is calculated in BASE UNIT.
    |
    */

    $bbf_by_product = [];

    if ($from_date) {

        $bbf_sql = "SELECT
                        sl.product_id,

                        COALESCE(
                            SUM(
                                (
                                    sl.qty_in *
                                    COALESCE(
                                        CASE
                                            WHEN u.base_unit_id IS NOT NULL
                                                 AND u.unit_scope = 'universal'
                                            THEN u.conversion_factor

                                            WHEN puc.conversion_factor IS NOT NULL
                                            THEN puc.conversion_factor

                                            ELSE 1
                                        END,
                                        1
                                    )
                                )
                                -
                                (
                                    sl.qty_out *
                                    COALESCE(
                                        CASE
                                            WHEN u.base_unit_id IS NOT NULL
                                                 AND u.unit_scope = 'universal'
                                            THEN u.conversion_factor

                                            WHEN puc.conversion_factor IS NOT NULL
                                            THEN puc.conversion_factor

                                            ELSE 1
                                        END,
                                        1
                                    )
                                )
                            ),
                            0
                        ) AS bbf

                    FROM stock_ledger sl

                    JOIN products p
                        ON sl.product_id = p.id
                       AND sl.tenant_id = p.tenant_id

                    LEFT JOIN uom u
                        ON sl.unit_id = u.id

                    LEFT JOIN product_uom_conversions puc
                        ON p.id = puc.product_id
                       AND sl.unit_id = puc.uom_id

                    LEFT JOIN purchase_invoice pi
                        ON sl.reference_table = 'purchase_invoice'
                       AND sl.reference_id = pi.id

                    LEFT JOIN sale_invoice si
                        ON sl.reference_table = 'sale_invoice'
                       AND sl.reference_id = si.id

                    WHERE sl.tenant_id = ?
                      AND p.product_type = 'physical'
                      AND sl.transaction_date < ?";

        $bbf_params = [
            $tenant_id,
            $from_date
        ];


        /*
        |--------------------------------------------------------------------------
        | Product filter
        |--------------------------------------------------------------------------
        */

        if ($product_id) {

            $bbf_sql .= " AND sl.product_id = ?";
            $bbf_params[] = $product_id;
        }


        /*
        |--------------------------------------------------------------------------
        | Vendor filter
        |--------------------------------------------------------------------------
        */

        if ($vendor_id) {

            $bbf_sql .= " AND p.vendor_id = ?";
            $bbf_params[] = $vendor_id;
        }


        /*
        |--------------------------------------------------------------------------
        | Customer filter
        |--------------------------------------------------------------------------
        */

        if ($customer_id) {

            $bbf_sql .= " AND si.customer_id = ?";
            $bbf_params[] = $customer_id;
        }


        /*
        |--------------------------------------------------------------------------
        | Company filter
        |--------------------------------------------------------------------------
        */

        if ($company_id) {

            $bbf_sql .= " AND (
                                pi.company_id = ?
                                OR
                                si.company_id = ?
                              )";

            $bbf_params[] = $company_id;
            $bbf_params[] = $company_id;
        }


        /*
        |--------------------------------------------------------------------------
        | Group by product
        |--------------------------------------------------------------------------
        */

        $bbf_sql .= " GROUP BY sl.product_id";


        $bbf_stmt = $pdo->prepare($bbf_sql);
        $bbf_stmt->execute($bbf_params);

        foreach ($bbf_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

            $bbf_by_product[$row['product_id']] =
                (float)$row['bbf'];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MAIN ITEM-WISE STOCK LEDGER QUERY
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | qty_in / qty_out are converted into BASE UNIT here.
    |
    | Example:
    |
    | Carton = 10 Piece
    | Dozen  = 12 Piece
    | Piece  = 1 Piece
    |
    | Therefore:
    |
    | 1 Carton = 10
    | 1 Dozen  = 12
    | 3 Piece  = 3
    |
    | Total Opening = 25 Piece
    |
    */

    $sql = "SELECT

                sl.id,

                sl.product_id,

                p.name AS product_name,

                sl.transaction_date,

                sl.transaction_type,

                sl.batch_no,

                sl.expiry_date AS batch_expiry_date,

                COALESCE(
                    pi.bill_no,
                    si.bill_no
                ) AS invoice_number,

                vs.supplier_name AS company_name,


                /*
                |--------------------------------------------------------------------------
                | CONVERT QTY IN TO BASE UNIT
                |--------------------------------------------------------------------------
                */

                (
                    sl.qty_in *
                    COALESCE(
                        CASE

                            WHEN u.base_unit_id IS NOT NULL
                                 AND u.unit_scope = 'universal'
                            THEN u.conversion_factor

                            WHEN puc.conversion_factor IS NOT NULL
                            THEN puc.conversion_factor

                            ELSE 1

                        END,
                        1
                    )
                ) AS qty_in,


                /*
                |--------------------------------------------------------------------------
                | CONVERT QTY OUT TO BASE UNIT
                |--------------------------------------------------------------------------
                */

                (
                    sl.qty_out *
                    COALESCE(
                        CASE

                            WHEN u.base_unit_id IS NOT NULL
                                 AND u.unit_scope = 'universal'
                            THEN u.conversion_factor

                            WHEN puc.conversion_factor IS NOT NULL
                            THEN puc.conversion_factor

                            ELSE 1

                        END,
                        1
                    )
                ) AS qty_out,


                sl.unit_cost,


                /*
                |--------------------------------------------------------------------------
                | ALWAYS SHOW BASE UNIT
                |--------------------------------------------------------------------------
                */

                COALESCE(
                    base_u.uom_name,
                    u.uom_name,
                    'L'
                ) AS unit_symbol,


                /*
                |--------------------------------------------------------------------------
                | PARTY NAME
                |--------------------------------------------------------------------------
                */

                CASE

                    WHEN sl.reference_table = 'purchase_invoice'
                    THEN s.supplier_name

                    WHEN sl.reference_table = 'sale_invoice'
                    THEN c.customer_name

                    ELSE NULL

                END AS party_name,


                /*
                |--------------------------------------------------------------------------
                | CUSTOMER / SUPPLIER NAME
                |--------------------------------------------------------------------------
                */

                CASE

                    WHEN sl.reference_table = 'sale_invoice'
                    THEN c.customer_name

                    WHEN sl.reference_table = 'sale_return'
                    THEN c2.customer_name

                    WHEN sl.reference_table = 'purchase_invoice'
                    THEN s.supplier_name

                    WHEN sl.reference_table = 'purchase_return'
                    THEN s2.supplier_name

                    ELSE NULL

                END AS customer_name


            FROM stock_ledger sl


            JOIN products p
                ON sl.product_id = p.id
               AND sl.tenant_id = p.tenant_id


            LEFT JOIN suppliers vs
                ON p.vendor_id = vs.id


            LEFT JOIN uom u
                ON sl.unit_id = u.id


            LEFT JOIN uom base_u
                ON COALESCE(
                       u.base_unit_id,
                       u.id
                   ) = base_u.id


            LEFT JOIN product_uom_conversions puc
                ON p.id = puc.product_id
               AND sl.unit_id = puc.uom_id


            LEFT JOIN purchase_invoice pi
                ON sl.reference_table = 'purchase_invoice'
               AND sl.reference_id = pi.id


            LEFT JOIN suppliers s
                ON pi.supplier_id = s.id


            LEFT JOIN sale_invoice si
                ON sl.reference_table = 'sale_invoice'
               AND sl.reference_id = si.id


            LEFT JOIN customers c
                ON si.customer_id = c.id


            LEFT JOIN sale_return sr
                ON sl.reference_table = 'sale_return'
               AND sl.reference_id = sr.id


            LEFT JOIN customers c2
                ON sr.customer_id = c2.id


            LEFT JOIN purchase_return pr
                ON sl.reference_table = 'purchase_return'
               AND sl.reference_id = pr.id


            LEFT JOIN suppliers s2
                ON pr.supplier_id = s2.id


            WHERE sl.tenant_id = ?";


    $params = [
        $tenant_id
    ];


    /*
    |--------------------------------------------------------------------------
    | PRODUCT FILTER
    |--------------------------------------------------------------------------
    */

    if ($product_id) {

        $sql .= " AND sl.product_id = ?";
        $params[] = $product_id;
    }


    /*
    |--------------------------------------------------------------------------
    | VENDOR FILTER
    |--------------------------------------------------------------------------
    */

    if ($vendor_id) {

        $sql .= " AND p.vendor_id = ?";
        $params[] = $vendor_id;
    }


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER FILTER
    |--------------------------------------------------------------------------
    */

    if ($customer_id) {

        $sql .= " AND si.customer_id = ?";
        $params[] = $customer_id;
    }


    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    */

    if ($from_date) {

        $sql .= " AND sl.transaction_date >= ?";
        $params[] = $from_date;
    }


    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    */

    if ($to_date) {

        $sql .= " AND sl.transaction_date <= ?";
        $params[] = $to_date;
    }


    /*
    |--------------------------------------------------------------------------
    | COMPANY FILTER
    |--------------------------------------------------------------------------
    */

    if ($company_id) {

        $sql .= " AND (
                            pi.company_id = ?
                            OR
                            si.company_id = ?
                          )";

        $params[] = $company_id;
        $params[] = $company_id;
    }


    /*
    |--------------------------------------------------------------------------
    | ORDER BY
    |--------------------------------------------------------------------------
    */

    $sql .= " ORDER BY
                    sl.transaction_date ASC,
                    sl.id ASC";


    /*
    |--------------------------------------------------------------------------
    | EXECUTE QUERY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $raw_ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | MERGE OPENING STOCK
    |--------------------------------------------------------------------------
    |
    | THIS IS THE MAIN FIX.
    |
    | Example database rows:
    |
    | 4473 = 1 Carton = 10 Piece
    | 4474 = 1 Dozen  = 12 Piece
    | 4475 = 3 Piece  = 3 Piece
    |
    | These will become:
    |
    | Opening Stock = 25 Piece
    |
    | Only Opening Stock is merged.
    |
    | Purchase / Sale / Returns remain individual rows.
    |
    | IMPORTANT (batch fix):
    | Rows are only merged together when they share the SAME product,
    | SAME date, AND SAME batch_no/expiry_date. This is what lets a
    | single opening entry's multiple unit-type rows (Carton + Dozen +
    | Piece) collapse into one row, while two DIFFERENT opening entries
    | for different batches on the same date stay as separate rows
    | instead of being silently summed into one.
    |
    |--------------------------------------------------------------------------
    */

    $ledger = [];

    $opening_groups = [];


    foreach ($raw_ledger as $entry) {


        /*
        |--------------------------------------------------------------------------
        | OPENING STOCK
        |--------------------------------------------------------------------------
        */

        if ($entry['transaction_type'] === 'Opening Stock') {


            /*
            |--------------------------------------------------------------------------
            | GROUP KEY
            |--------------------------------------------------------------------------
            |
            | Same product + same transaction date + same batch/expiry
            |
            | So:
            |
            | cream + 2026-08-28 + batch B-001
            |
            | = ONE opening row
            |
            | but a different batch on the SAME date stays as its own row.
            |
            |--------------------------------------------------------------------------
            */

            $group_key =
                $entry['product_id']
                . '|'
                . $entry['transaction_date']
                . '|'
                . ($entry['batch_no'] ?? '')
                . '|'
                . ($entry['batch_expiry_date'] ?? '');


            /*
            |--------------------------------------------------------------------------
            | FIRST OPENING ROW
            |--------------------------------------------------------------------------
            */

            if (!isset($opening_groups[$group_key])) {

                $opening_groups[$group_key] = $entry;


                /*
                |--------------------------------------------------------------------------
                | Reset quantities
                |--------------------------------------------------------------------------
                */

                $opening_groups[$group_key]['qty_in'] = 0;

                $opening_groups[$group_key]['qty_out'] = 0;


                /*
                |--------------------------------------------------------------------------
                | Keep first ID
                |--------------------------------------------------------------------------
                |
                | Needed for correct sorting.
                |
                |--------------------------------------------------------------------------
                */

                $opening_groups[$group_key]['id'] =
                    (int)$entry['id'];


                /*
                |--------------------------------------------------------------------------
                | BASE UNIT
                |--------------------------------------------------------------------------
                */

                $opening_groups[$group_key]['unit_symbol'] =
                    $entry['unit_symbol'] ?: 'L';
            }


            /*
            |--------------------------------------------------------------------------
            | ADD CONVERTED QTY
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | SQL has already converted:
            |
            | Carton 1 -> 10
            | Dozen  1 -> 12
            | Piece  3 -> 3
            |
            | So here simply SUM them.
            |
            |--------------------------------------------------------------------------
            */

            $opening_groups[$group_key]['qty_in'] +=
                (float)$entry['qty_in'];


            $opening_groups[$group_key]['qty_out'] +=
                (float)$entry['qty_out'];
        }


        /*
        |--------------------------------------------------------------------------
        | NORMAL TRANSACTIONS
        |--------------------------------------------------------------------------
        |
        | Purchase / Sale / Return / FOC etc.
        | remain separate.
        |
        |--------------------------------------------------------------------------
        */

        else {

            $ledger[] = $entry;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ADD MERGED OPENING ROWS
    |--------------------------------------------------------------------------
    */

    foreach ($opening_groups as $opening) {

        $ledger[] = $opening;
    }


    /*
    |--------------------------------------------------------------------------
    | SORT COMPLETE LEDGER
    |--------------------------------------------------------------------------
    */

    usort(
        $ledger,
        function ($a, $b) {

            /*
            |--------------------------------------------------------------------------
            | First sort by date
            |--------------------------------------------------------------------------
            */

            $date_compare = strcmp(
                $a['transaction_date'],
                $b['transaction_date']
            );


            if ($date_compare !== 0) {

                return $date_compare;
            }


            /*
            |--------------------------------------------------------------------------
            | Same date -> sort by ID
            |--------------------------------------------------------------------------
            */

            return (
                (int)($a['id'] ?? 0)
            ) <=> (
                (int)($b['id'] ?? 0)
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | RUNNING BALANCE PER PRODUCT
    |--------------------------------------------------------------------------
    |
    | Each product gets its own running balance.
    |
    |--------------------------------------------------------------------------
    */

    $balances = $bbf_by_product;


    foreach ($ledger as &$entry) {

        $pid = $entry['product_id'];


        if (!isset($balances[$pid])) {

            $balances[$pid] = 0;
        }


        /*
        |--------------------------------------------------------------------------
        | ADD IN / SUBTRACT OUT
        |--------------------------------------------------------------------------
        */

        $balances[$pid] +=
            (float)$entry['qty_in']
            -
            (float)$entry['qty_out'];


        /*
        |--------------------------------------------------------------------------
        | RUNNING BALANCE
        |--------------------------------------------------------------------------
        */

        $entry['balance'] =
            $balances[$pid];


        /*
        |--------------------------------------------------------------------------
        | STOCK VALUE
        |--------------------------------------------------------------------------
        */

        $entry['value'] =
            $balances[$pid]
            *
            (float)$entry['unit_cost'];
    }


    unset($entry);


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'data' => $ledger,
        'currency' => $currency_symbol
    ]);

    break;


        case 'branch-ledger':
            $branch_id = $_GET['branch_id'] ?? null;
            $company_id = $_GET['company_id'] ?? null;
            $from_date = $_GET['from_date'] ?? null;
            $to_date = $_GET['to_date'] ?? null;

            if (!$branch_id) {
                echo json_encode(['success' => false, 'message' => 'Branch ID required']);
                exit;
            }

            $sql = "SELECT 
                        sl.transaction_date,
                        p.name as product_name,
                        sl.transaction_type,
                        sl.batch_no,
                        sl.expiry_date AS batch_expiry_date,
                        sl.qty_in * COALESCE(CASE WHEN u.unit_scope = 'universal' THEN u.conversion_factor ELSE puc.conversion_factor END, 1) as qty_in,
                        sl.qty_out * COALESCE(CASE WHEN u.unit_scope = 'universal' THEN u.conversion_factor ELSE puc.conversion_factor END, 1) as qty_out,
                        sl.unit_cost,
                        COALESCE(base_u.uom_name, u.uom_name, 'L') as unit_symbol
                    FROM stock_ledger sl
                    JOIN products p ON sl.product_id = p.id
                    LEFT JOIN uom u ON sl.unit_id = u.id
                    LEFT JOIN uom base_u ON COALESCE(u.base_unit_id, u.id) = base_u.id
                    LEFT JOIN product_uom_conversions puc ON p.id = puc.product_id AND sl.unit_id = puc.uom_id
                    LEFT JOIN purchase_invoice pi ON sl.reference_table = 'purchase_invoice' AND sl.reference_id = pi.id
                    LEFT JOIN sale_invoice si ON sl.reference_table = 'sale_invoice' AND sl.reference_id = si.id
                    WHERE sl.tenant_id = ? AND sl.branch_id = ? AND p.product_type = 'physical'";

            $child_sql = "SELECT id FROM branches WHERE (id = ? OR parent_branch_id = ?) AND tenant_id = ?";
            $child_stmt = $pdo->prepare($child_sql);
            $child_stmt->execute([$branch_id, $branch_id, $tenant_id]);
            $branch_ids = $child_stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($branch_ids)) {
                $placeholders = str_repeat('?,', count($branch_ids) - 1) . '?';
                $sql = str_replace('sl.branch_id = ?', "sl.branch_id IN ($placeholders)", $sql);
                $params = array_merge([$tenant_id], $branch_ids);
            } else {
                $params = [$tenant_id, $branch_id];
            }

            if ($from_date) {
                $sql .= " AND sl.transaction_date >= ?";
                $params[] = $from_date;
            }

            if ($to_date) {
                $sql .= " AND sl.transaction_date <= ?";
                $params[] = $to_date;
            }

            if ($company_id) {
                $sql .= " AND (pi.company_id = ? OR si.company_id = ?)";
                $params[] = $company_id;
                $params[] = $company_id;
            }

            $sql .= " ORDER BY sl.transaction_date DESC, sl.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $ledger, 'currency' => $currency_symbol]);
            break;

        case 'stats':
            $company_id = $_GET['company_id'] ?? null;
            $valuation_method = $_GET['valuation_method'] ?? 'AVCO';

            $allowed_methods = ['AVCO', 'FIFO', 'LIFO', 'TRADE_PRICE'];
            if (!in_array($valuation_method, $allowed_methods, true)) {
                $valuation_method = 'AVCO';
            }

            // Conversion factor for base units
            $conversionFactor = "COALESCE(CASE WHEN u.unit_scope = 'universal' THEN u.conversion_factor ELSE puc.conversion_factor END, 1)";

            // Unit cost calculation based on valuation method
            $unitCostCalc = $valuation_method === 'TRADE_PRICE'
                ? "COALESCE(p.trade_price, 0)"
                : "COALESCE(AVG(CASE WHEN sl.qty_in > 0 THEN sl.unit_cost ELSE NULL END), 0)";

            $stats_sql = "SELECT 
                            SUM(COALESCE(stock_data.stock_value, 0)) as total_value,
                            SUM(CASE WHEN stock_data.status = 'in-stock' THEN 1 ELSE 0 END) as in_stock,
                            SUM(CASE WHEN stock_data.status = 'low-stock' THEN 1 ELSE 0 END) as low_stock,
                            SUM(CASE WHEN stock_data.status = 'out-of-stock' THEN 1 ELSE 0 END) as out_of_stock,
                            SUM(CASE WHEN stock_data.status = 'overstock' THEN 1 ELSE 0 END) as overstock
                        FROM (
                            SELECT 
                                COALESCE(SUM(
                                    (sl.qty_in * {$conversionFactor}) - 
                                    (sl.qty_out * {$conversionFactor})
                                ), 0) as current_stock_base,
                                {$unitCostCalc} as unit_cost,
                                COALESCE(SUM(
                                    (sl.qty_in * {$conversionFactor}) - 
                                    (sl.qty_out * {$conversionFactor})
                                ), 0) * {$unitCostCalc} as stock_value,
                                p.min_stock_level,
                                p.max_stock_level,
                                CASE 
                                    WHEN COALESCE(SUM(
                                        (sl.qty_in * {$conversionFactor}) - 
                                        (sl.qty_out * {$conversionFactor})
                                    ), 0) <= 0 THEN 'out-of-stock'
                                    WHEN COALESCE(SUM(
                                        (sl.qty_in * {$conversionFactor}) - 
                                        (sl.qty_out * {$conversionFactor})
                                    ), 0) < COALESCE(p.min_stock_level, 0) THEN 'low-stock'
                                    WHEN p.max_stock_level > 0 AND COALESCE(SUM(
                                        (sl.qty_in * {$conversionFactor}) - 
                                        (sl.qty_out * {$conversionFactor})
                                    ), 0) > p.max_stock_level THEN 'overstock'
                                    ELSE 'in-stock'
                                END as status
                            FROM stock_ledger sl
                            JOIN products p ON sl.product_id = p.id AND sl.tenant_id = p.tenant_id
                            LEFT JOIN uom u ON sl.unit_id = u.id
                            LEFT JOIN product_uom_conversions puc ON p.id = puc.product_id AND sl.unit_id = puc.uom_id
                            LEFT JOIN purchase_invoice pi ON sl.reference_table = 'purchase_invoice' AND sl.reference_id = pi.id
                            LEFT JOIN sale_invoice si ON sl.reference_table = 'sale_invoice' AND sl.reference_id = si.id
                            WHERE sl.tenant_id = ?" . ($company_id ? " AND (pi.company_id = ? OR si.company_id = ?)" : "") . " AND p.product_type = 'physical'
                            GROUP BY p.id, sl.branch_id, sl.account_id
                        ) as stock_data";

            $stats_stmt = $pdo->prepare($stats_sql);
            $stats_params = [$tenant_id];
            if ($company_id) {
                $stats_params[] = $company_id;
                $stats_params[] = $company_id;
            }
            $stats_stmt->execute($stats_params);
            $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $stats, 'currency' => $currency_symbol]);
            break;

        case 'customer-ledger':
            $customer_id = $_GET['customer_id'] ?? null;
            $product_id = $_GET['product_id'] ?? null;
            $from_date = $_GET['from_date'] ?? null;
            $to_date = $_GET['to_date'] ?? null;

            // NOTE: Customer/Product used to be mandatory here — the tab now
            // auto-loads full tenant-wide data by default (same pattern as the
            // other ledger tabs), with customer_id/product_id as optional
            // narrowing filters, so that requirement was removed.

            $sql = "SELECT 
                        sl.transaction_date,
                        si.bill_no as invoice_number,
                        p.name as product_name,
                        COALESCE(b.branch_name, 'No Branch') as branch_name,
                        sl.transaction_type,
                        sl.batch_no,
                        sl.expiry_date AS batch_expiry_date,
                        sl.qty_in * COALESCE(CASE WHEN u.unit_scope = 'universal' THEN u.conversion_factor ELSE puc.conversion_factor END, 1) as qty_in,
                        sl.qty_out * COALESCE(CASE WHEN u.unit_scope = 'universal' THEN u.conversion_factor ELSE puc.conversion_factor END, 1) as qty_out,
                        sl.unit_cost,
                        COALESCE(base_u.uom_name, u.uom_name, 'L') as unit_symbol
                    FROM stock_ledger sl
                    JOIN products p ON sl.product_id = p.id
                    LEFT JOIN branches b ON sl.branch_id = b.id
                    LEFT JOIN uom u ON sl.unit_id = u.id
                    LEFT JOIN uom base_u ON COALESCE(u.base_unit_id, u.id) = base_u.id
                    LEFT JOIN product_uom_conversions puc ON p.id = puc.product_id AND sl.unit_id = puc.uom_id
                    JOIN sale_invoice si ON sl.reference_table = 'sale_invoice' AND sl.reference_id = si.id
                    WHERE sl.tenant_id = ? AND p.product_type = 'physical'";

            $params = [$tenant_id];

            if ($customer_id) {
                $sql .= " AND si.customer_id = ?";
                $params[] = $customer_id;
            }

            if ($product_id) {
                $sql .= " AND p.id = ?";
                $params[] = $product_id;
            }

            if ($from_date) {
                $sql .= " AND sl.transaction_date >= ?";
                $params[] = $from_date;
            }

            if ($to_date) {
                $sql .= " AND sl.transaction_date <= ?";
                $params[] = $to_date;
            }

            $sql .= " ORDER BY sl.transaction_date DESC, sl.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $ledger, 'currency' => $currency_symbol]);
            break;

       case 'item-movement':

    $product_id = $_GET['product_id'] ?? null;
    $branch_id  = $_GET['branch_id'] ?? null;
    $vendor_id  = $_GET['vendor_id'] ?? null;
    $from_date  = $_GET['from_date'] ?? null;
    $to_date    = $_GET['to_date'] ?? null;


    /*
    |--------------------------------------------------------------------------
    | BASE UNIT CONVERSION
    |--------------------------------------------------------------------------
    |
    | Piece  = 1
    | Dozen  = 12
    | Carton = product-specific conversion
    |
    |--------------------------------------------------------------------------
    */

    $conversionFactor = "
        COALESCE(
            CASE

                WHEN u.base_unit_id IS NOT NULL
                     AND u.unit_scope = 'universal'
                THEN u.conversion_factor

                WHEN puc.conversion_factor IS NOT NULL
                THEN puc.conversion_factor

                ELSE 1

            END,
            1
        )
    ";


    /*
    |--------------------------------------------------------------------------
    | OPENING BALANCE
    |--------------------------------------------------------------------------
    |
    | IMPORTANT (fix):
    |
    | This must be the balance as of the START of from_date - i.e. every
    | transaction strictly BEFORE from_date, PLUS any 'Opening Stock' rows
    | dated exactly ON from_date (those represent an opening balance entry,
    | not a period movement).
    |
    | Previously this used "transaction_date <= from_date" for ALL
    | transaction types, which double-counted anything dated exactly on
    | from_date: a Purchase/Sale on that date was added into Opening here
    | AND added again into Purchase/Sale below (which uses ">= from_date").
    | That's what was making the Opening figure wrong.
    |
    | Example:
    |
    | 1 Carton = 10
    | 1 Dozen  = 12
    | 3 Piece  = 3
    |
    | Opening = 25 Piece
    |
    |--------------------------------------------------------------------------
    */

    $opening_sql = "
        SELECT

            sl.product_id,

            COALESCE(
                SUM(
                    (
                        sl.qty_in * {$conversionFactor}
                    )
                    -
                    (
                        sl.qty_out * {$conversionFactor}
                    )
                ),
                0
            ) AS opening


        FROM stock_ledger sl


        JOIN products p
            ON sl.product_id = p.id
           AND sl.tenant_id = p.tenant_id


        LEFT JOIN uom u
            ON sl.unit_id = u.id


        LEFT JOIN product_uom_conversions puc
            ON p.id = puc.product_id
           AND sl.unit_id = puc.uom_id


        WHERE sl.tenant_id = ?

          AND p.product_type = 'physical'
    ";


    $opening_params = [
        $tenant_id
    ];


    /*
    |--------------------------------------------------------------------------
    | PRODUCT FILTER
    |--------------------------------------------------------------------------
    */

    if ($product_id) {

        $opening_sql .= " AND sl.product_id = ?";
        $opening_params[] = $product_id;
    }


    /*
    |--------------------------------------------------------------------------
    | BRANCH FILTER
    |--------------------------------------------------------------------------
    */

    if ($branch_id) {

        $opening_sql .= " AND sl.branch_id = ?";
        $opening_params[] = $branch_id;
    }


    /*
    |--------------------------------------------------------------------------
    | VENDOR FILTER
    |--------------------------------------------------------------------------
    */

    if ($vendor_id) {

        $opening_sql .= " AND p.vendor_id = ?";
        $opening_params[] = $vendor_id;
    }


    /*
    |--------------------------------------------------------------------------
    | DATE FILTER
    |--------------------------------------------------------------------------
    |
    | IMPORTANT (fix):
    |
    | Strictly BEFORE from_date for every transaction type, OR exactly ON
    | from_date but ONLY when it's an 'Opening Stock' row. This avoids
    | double-counting a same-day Purchase/Sale that the period-movement
    | query below also picks up via ">= from_date".
    |
    | IMPORTANT (fix #2 - no from_date selected):
    |
    | When from_date is NOT provided, there is no date split to rely on,
    | so previously ALL transaction types (Opening Stock + Purchase +
    | Sale + Returns) were summed together into "opening" - which made
    | opening equal the CURRENT balance, not the opening balance. That
    | current-balance figure then got treated as opening AND the same
    | Purchase/Sale rows were subtracted again in the period-movement
    | totals below, producing a wrong (double-subtracted) Closing.
    |
    | Fix: with no from_date, opening must only ever be the recorded
    | 'Opening Stock' entries.
    |
    |--------------------------------------------------------------------------
    */

    if ($from_date) {

        $opening_sql .= " AND (sl.transaction_date < ? OR (sl.transaction_date = ? AND sl.transaction_type = 'Opening Stock'))";
        $opening_params[] = $from_date;
        $opening_params[] = $from_date;

    } else {

        $opening_sql .= " AND sl.transaction_type = 'Opening Stock'";
    }


    /*
    |--------------------------------------------------------------------------
    | GET OPENING
    |--------------------------------------------------------------------------
    */

    $opening_sql .= " GROUP BY sl.product_id";


    $opening_stmt = $pdo->prepare($opening_sql);
    $opening_stmt->execute($opening_params);

    $opening_rows = $opening_stmt->fetchAll(PDO::FETCH_ASSOC);


    $opening_by_product = [];


    foreach ($opening_rows as $row) {

        $opening_by_product[$row['product_id']] =
            (float)$row['opening'];
    }


    /*
    |--------------------------------------------------------------------------
    | PERIOD MOVEMENTS
    |--------------------------------------------------------------------------
    |
    | Opening Stock ko yahan include nahi karna.
    |
    | Purchase / Sale / Returns only.
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            sl.product_id,

            p.name AS product_name,


            /*
            |--------------------------------------------------------------------------
            | BASE UNIT NAME
            |--------------------------------------------------------------------------
            */

            COALESCE(
                base_u.uom_name,
                u.uom_name,
                'L'
            ) AS unit_symbol,


            /*
            |--------------------------------------------------------------------------
            | PURCHASE
            |--------------------------------------------------------------------------
            */

            COALESCE(
                SUM(
                    CASE
                        WHEN sl.reference_table = 'purchase_invoice'
                        THEN sl.qty_in * {$conversionFactor}
                        ELSE 0
                    END
                ),
                0
            ) AS purchase,


            /*
            |--------------------------------------------------------------------------
            | SALE
            |--------------------------------------------------------------------------
            */

            COALESCE(
                SUM(
                    CASE
                        WHEN sl.reference_table = 'sale_invoice'
                        THEN sl.qty_out * {$conversionFactor}
                        ELSE 0
                    END
                ),
                0
            ) AS sale,


            /*
            |--------------------------------------------------------------------------
            | CREDIT NOTE / SALE RETURN
            |--------------------------------------------------------------------------
            */

            COALESCE(
                SUM(
                    CASE
                        WHEN sl.reference_table IN (
                            'sale_return',
                            'credit_note'
                        )
                        THEN sl.qty_in * {$conversionFactor}
                        ELSE 0
                    END
                ),
                0
            ) AS credit_note,


            /*
            |--------------------------------------------------------------------------
            | DEBIT NOTE / PURCHASE RETURN
            |--------------------------------------------------------------------------
            */

            COALESCE(
                SUM(
                    CASE
                        WHEN sl.reference_table IN (
                            'purchase_return',
                            'debit_note'
                        )
                        THEN sl.qty_out * {$conversionFactor}
                        ELSE 0
                    END
                ),
                0
            ) AS debit_note


        FROM stock_ledger sl


        JOIN products p
            ON sl.product_id = p.id
           AND sl.tenant_id = p.tenant_id


        LEFT JOIN uom u
            ON sl.unit_id = u.id


        LEFT JOIN uom base_u
            ON COALESCE(
                   u.base_unit_id,
                   u.id
               ) = base_u.id


        LEFT JOIN product_uom_conversions puc
            ON p.id = puc.product_id
           AND sl.unit_id = puc.uom_id


        WHERE sl.tenant_id = ?

          AND p.product_type = 'physical'
    ";


    $params = [
        $tenant_id
    ];


    /*
    |--------------------------------------------------------------------------
    | PRODUCT FILTER
    |--------------------------------------------------------------------------
    */

    if ($product_id) {

        $sql .= " AND sl.product_id = ?";
        $params[] = $product_id;
    }


    /*
    |--------------------------------------------------------------------------
    | BRANCH FILTER
    |--------------------------------------------------------------------------
    */

    if ($branch_id) {

        $sql .= " AND sl.branch_id = ?";
        $params[] = $branch_id;
    }


    /*
    |--------------------------------------------------------------------------
    | VENDOR FILTER
    |--------------------------------------------------------------------------
    */

    if ($vendor_id) {

        $sql .= " AND p.vendor_id = ?";
        $params[] = $vendor_id;
    }


    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    |
    | Opening Stock is already handled above.
    |
    | So period movements should start AFTER opening date.
    |
    |--------------------------------------------------------------------------
    */

    if ($from_date) {

        $sql .= " AND sl.transaction_date >= ?";
        $params[] = $from_date;
    }


    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    */

    if ($to_date) {

        $sql .= " AND sl.transaction_date <= ?";
        $params[] = $to_date;
    }


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | Opening Stock must NOT be counted as Purchase/Sale movement.
    |--------------------------------------------------------------------------
    */

    $sql .= "
        AND sl.transaction_type <> 'Opening Stock'
    ";


    /*
    |--------------------------------------------------------------------------
    | GROUP
    |--------------------------------------------------------------------------
    */

    $sql .= "
        GROUP BY
            sl.product_id,
            p.name,
            unit_symbol

        ORDER BY
            p.name ASC
    ";


    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | FINAL ONE ROW PER PRODUCT
    |--------------------------------------------------------------------------
    */

    $movement = [];

    $seen = [];


    /*
    |--------------------------------------------------------------------------
    | PRODUCTS HAVING MOVEMENTS
    |--------------------------------------------------------------------------
    */

    foreach ($rows as $row) {

        $pid = $row['product_id'];

        $seen[$pid] = true;


        /*
        |--------------------------------------------------------------------------
        | OPENING
        |--------------------------------------------------------------------------
        */

        $opening =
            $opening_by_product[$pid] ?? 0;


        /*
        |--------------------------------------------------------------------------
        | MOVEMENTS
        |--------------------------------------------------------------------------
        */

        $purchase =
            (float)$row['purchase'];

        $sale =
            (float)$row['sale'];

        $credit_note =
            (float)$row['credit_note'];

        $debit_note =
            (float)$row['debit_note'];


        /*
        |--------------------------------------------------------------------------
        | CLOSING
        |--------------------------------------------------------------------------
        */

        $closing =
            $opening
            +
            $purchase
            +
            $credit_note
            -
            $sale
            -
            $debit_note;


        /*
        |--------------------------------------------------------------------------
        | RESULT
        |--------------------------------------------------------------------------
        */

        $movement[] = [

            'product_id' =>
                $pid,

            'product_name' =>
                $row['product_name'],

            'unit_symbol' =>
                $row['unit_symbol'] ?: 'L',

            'opening' =>
                $opening,

            'purchase' =>
                $purchase,

            'sale' =>
                $sale,

            'credit_note' =>
                $credit_note,

            'debit_note' =>
                $debit_note,

            'closing' =>
                $closing
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCTS HAVING ONLY OPENING STOCK
    |--------------------------------------------------------------------------
    |
    | If product has Opening Stock but no Purchase/Sale in selected period,
    | it must still appear.
    |
    |--------------------------------------------------------------------------
    */

    foreach ($opening_by_product as $pid => $opening) {

        if (isset($seen[$pid])) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | PRODUCT NAME
        |--------------------------------------------------------------------------
        */

        $name_stmt = $pdo->prepare("
            SELECT name
            FROM products
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ");


        $name_stmt->execute([
            $pid,
            $tenant_id
        ]);


        $pname =
            $name_stmt->fetchColumn()
            ?: 'Unknown';


        /*
        |--------------------------------------------------------------------------
        | GET BASE UNIT
        |--------------------------------------------------------------------------
        */

        $unit_stmt = $pdo->prepare("
            SELECT
                COALESCE(
                    base_u.uom_name,
                    u.uom_name,
                    'L'
                ) AS unit_symbol

            FROM products p

            LEFT JOIN product_uom_conversions puc
                ON p.id = puc.product_id

            LEFT JOIN uom u
                ON puc.uom_id = u.id

            LEFT JOIN uom base_u
                ON COALESCE(
                       u.base_unit_id,
                       u.id
                   ) = base_u.id

            WHERE p.id = ?
              AND p.tenant_id = ?

            LIMIT 1
        ");


        $unit_stmt->execute([
            $pid,
            $tenant_id
        ]);


        $unit_symbol =
            $unit_stmt->fetchColumn()
            ?: 'Piece';


        /*
        |--------------------------------------------------------------------------
        | ADD PRODUCT
        |--------------------------------------------------------------------------
        */

        $movement[] = [

            'product_id' =>
                $pid,

            'product_name' =>
                $pname,

            'unit_symbol' =>
                $unit_symbol,

            'opening' =>
                (float)$opening,

            'purchase' =>
                0,

            'sale' =>
                0,

            'credit_note' =>
                0,

            'debit_note' =>
                0,

            'closing' =>
                (float)$opening
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | SORT BY PRODUCT NAME
    |--------------------------------------------------------------------------
    */

    usort(
        $movement,
        function ($a, $b) {

            return strcmp(
                $a['product_name'],
                $b['product_name']
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'data' => $movement,
        'currency' => $currency_symbol
    ]);

    break;


        case 'customers':
            $customers_sql = "SELECT id, customer_name FROM customers WHERE tenant_id = ? AND status = 'ACTIVE' ORDER BY customer_name";
            $customers_stmt = $pdo->prepare($customers_sql);
            $customers_stmt->execute([$tenant_id]);
            $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $customers]);
            break;

        case 'branches':
            $branches_sql = "
                SELECT
                    b.id,
                    b.branch_name,
                    b.branch_type,
                    b.parent_branch_id,
                    pb.branch_name as parent_name
                FROM user_branches ub
                JOIN branches b  ON ub.branch_id = b.id
                LEFT JOIN branches pb ON b.parent_branch_id = pb.id
                WHERE ub.tenant_id = ?
                  AND ub.user_id   = ?
                  AND b.is_active  = 1
                ORDER BY pb.branch_name, b.branch_name
            ";
            $branches_stmt = $pdo->prepare($branches_sql);
            $branches_stmt->execute([$tenant_id, $user_id]);
            $branches = $branches_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($branches as &$branch) {
                $type_map = [
                    'head_office' => 'Head Office',
                    'petrol_station' => 'Petrol Station',
                    'convenience_store' => 'Convenience Store',
                    'car_wash' => 'Car Wash',
                    'fuel_tank' => 'Fuel Tank',
                    'warehouse' => 'Warehouse',
                    'distribution_center' => 'Distribution Center'
                ];
                $type_display = $type_map[$branch['branch_type']] ?? $branch['branch_type'];
                $branch['display_name'] = $branch['branch_name'] . ' (' . $type_display . ')';
                if ($branch['parent_branch_id']) {
                    $branch['display_name'] = $branch['parent_name'] . ' > ' . $branch['display_name'];
                }
            }

            echo json_encode(['success' => true, 'data' => $branches]);
            break;

        case 'products':
            $products_sql = "SELECT id, name FROM products WHERE tenant_id = ? AND is_active = 1 AND product_type = 'physical'";
            $products_stmt = $pdo->prepare($products_sql);
            $products_stmt->execute([$tenant_id]);
            $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $products]);
            break;

        case 'inventory-types':
            $types_sql = "SELECT DISTINCT a.id, a.name FROM accounts a JOIN stock_ledger sl ON a.id = sl.account_id WHERE sl.tenant_id = ? ORDER BY a.name";
            $types_stmt = $pdo->prepare($types_sql);
            $types_stmt->execute([$tenant_id]);
            $types = $types_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $types]);
            break;

        case 'distributions':
            $dist_sql = "SELECT DISTINCT s.id, s.supplier_name FROM suppliers s JOIN products p ON s.id = p.vendor_id WHERE s.tenant_id = ? AND s.status = 'ACTIVE' ORDER BY s.supplier_name";
            $dist_stmt = $pdo->prepare($dist_sql);
            $dist_stmt->execute([$tenant_id]);
            $distributions = $dist_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $distributions]);
            break;

        case 'companies':
            // NOTE: this action actually backs the Supplier/Vendor picker on the
            // "Company-wise Ledger" tab, so it returns Suppliers (not the `companies` table).
            // 'company_name' key kept for frontend/JS compatibility.
            $companies_sql = "SELECT id, supplier_name as company_name FROM suppliers WHERE tenant_id = ? AND status = 'ACTIVE' ORDER BY supplier_name";
            $companies_stmt = $pdo->prepare($companies_sql);
            $companies_stmt->execute([$tenant_id]);
            $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $companies]);
            break;

        case 'detailed-ledger':
            $branchId = $_GET['branch_id'] ?? null;
            $productId = $_GET['product_id'] ?? null;
            $fromDate = $_GET['from_date'] ?? null;
            $toDate = $_GET['to_date'] ?? null;

            $sql = "SELECT 
                        sl.id,
                        sl.transaction_date,
                        p.name as product_name,
                        b.branch_name,
                        sl.transaction_type,
                        sl.batch_no,
                        sl.expiry_date AS batch_expiry_date,
                        sl.qty_in,
                        sl.qty_out,
                        sl.unit_cost,
                        COALESCE(u.uom_name, 'L') as unit_symbol
                    FROM stock_ledger sl
                    JOIN products p ON sl.product_id = p.id
                    JOIN branches b ON sl.branch_id = b.id
                    LEFT JOIN uom u ON sl.unit_id = u.id
                    LEFT JOIN purchase_invoice pi ON sl.reference_table = 'purchase_invoice' AND sl.reference_id = pi.id
                    LEFT JOIN sale_invoice si ON sl.reference_table = 'sale_invoice' AND sl.reference_id = si.id
                    WHERE sl.tenant_id = ? AND p.product_type = 'physical'";

            $params = [$tenant_id];

            if ($branchId) {
                $sql .= " AND sl.branch_id = ?";
                $params[] = $branchId;
            }

            if ($productId) {
                $sql .= " AND sl.product_id = ?";
                $params[] = $productId;
            }

            if ($fromDate) {
                $sql .= " AND sl.transaction_date >= ?";
                $params[] = $fromDate;
            }

            if ($toDate) {
                $sql .= " AND sl.transaction_date <= ?";
                $params[] = $toDate;
            }

            $sql .= " ORDER BY sl.transaction_date ASC, sl.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Debug tracking
            $focCount = count(array_filter($ledger, function ($row) {
                return strpos($row['transaction_type'], 'FOC') !== false;
            }));
            $focTransactions = array_filter($ledger, function ($row) {
                return strpos($row['transaction_type'], 'FOC') !== false;
            });

            // Error log for debugging
            error_log('=== Detailed Ledger Query Debug ===');
            error_log('Tenant: ' . $tenant_id . ', Branch: ' . ($branchId ?? 'ALL') . ', Product: ' . ($productId ?? 'ALL'));
            error_log('SQL: ' . $sql);
            error_log('Params: ' . json_encode($params));
            error_log('Total Rows Returned: ' . count($ledger));
            error_log('FOC Rows Found: ' . $focCount);
            if (!empty($focTransactions)) {
                error_log('FOC Details: ' . json_encode(array_values($focTransactions)));
            }

            echo json_encode([
                'success' => true,
                'data' => $ledger,
                'currency' => $currency_symbol,
                'debug' => [
                    'total_rows' => count($ledger),
                    'foc_count' => $focCount,
                    'sql_used' => $sql,
                    'params_used' => $params
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('Stock Position API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}