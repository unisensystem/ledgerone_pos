<?php
// ============================================================================
// PATH:
// server/api/sales/daily_product_sale_report/daily-product-sale-report.php
// ============================================================================

require_once '../../../../includes/connection.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');


// ============================================================================
// ONLY GET ALLOWED
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);

    exit;
}


// ============================================================================
// SESSION
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;


// ============================================================================
// AUTH CHECK
// ============================================================================

if (!$user_id || !$tenant_id) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}


try {

    // =========================================================================
    // ACTION
    // =========================================================================

    $action = $_GET['action'] ?? 'report';


    // =========================================================================
    // BASE CURRENCY
    // =========================================================================

    $currency_symbol = '$';

    try {

        $currency_sql = "
            SELECT c.symbol
            FROM tenant_currencies tc
            INNER JOIN ledgerone_public.currencies c
                ON tc.currency_id = c.id
            WHERE tc.tenant_id = ?
              AND tc.is_base_currency = 1
            LIMIT 1
        ";

        $currency_stmt = $pdo->prepare($currency_sql);

        $currency_stmt->execute([
            $tenant_id
        ]);

        $currency = $currency_stmt->fetch(PDO::FETCH_ASSOC);

        if ($currency && !empty($currency['symbol'])) {
            $currency_symbol = $currency['symbol'];
        }

    } catch (Exception $e) {

        // Currency failure should not stop the report.
        error_log(
            'Daily Sale Report Currency Error: ' .
            $e->getMessage()
        );
    }


    // =========================================================================
    // ACTION SWITCH
    // =========================================================================

    switch ($action) {


        // =====================================================================
        // REPORT
        // =====================================================================

        case 'report':

            // -----------------------------------------------------------------
            // FILTERS
            // -----------------------------------------------------------------

            $from_date = !empty($_GET['from_date'])
                ? trim($_GET['from_date'])
                : null;

            $to_date = !empty($_GET['to_date'])
                ? trim($_GET['to_date'])
                : null;

            $customer_id = !empty($_GET['customer_id'])
                ? trim($_GET['customer_id'])
                : null;

            $branch_id = !empty($_GET['branch_id'])
                ? trim($_GET['branch_id'])
                : null;

            $product_id = !empty($_GET['product_id'])
                ? trim($_GET['product_id'])
                : null;

            $batch_no = !empty($_GET['batch_no'])
                ? trim($_GET['batch_no'])
                : null;

            $expiry_date = !empty($_GET['expiry_date'])
                ? trim($_GET['expiry_date'])
                : null;

            // Salesman (sale officer) filter
            $sale_officer_id = !empty($_GET['sale_officer_id'])
                ? trim($_GET['sale_officer_id'])
                : null;

            // City filter (from customer's city_id)
            $city_id = !empty($_GET['city_id'])
                ? trim($_GET['city_id'])
                : null;

            // Area filter (from customer's area_id)
            $area_id = !empty($_GET['area_id'])
                ? trim($_GET['area_id'])
                : null;


            // -----------------------------------------------------------------
            // MAIN QUERY
            //
            // IMPORTANT:
            // batch_no and batch_expiry_date are in sale_invoice_items.
            //
            // FOC:
            // sii.foc_quantity is also directly in sale_invoice_items.
            //
            // Therefore:
            //
            // sii.batch_no
            // sii.batch_expiry_date
            // sii.foc_quantity
            //
            // NO stock_ledger JOIN is required.
            //
            // Salesman filter uses si.sale_officer_id directly.
            // City / Area filters use c.city_id / c.area_id directly,
            // since customers already carries those columns.
            // NO extra JOIN is required for filtering.
            // -----------------------------------------------------------------

            $sql = "
    SELECT

        si.id AS invoice_id,
        si.bill_no,
        si.sale_date,
        si.customer_id,
        si.branch_id,

        si.net_amount AS invoice_net_amount,
        (si.total_discount_percent
            + si.extra_discount_1_percent
            + si.extra_discount_2_percent) AS invoice_disc_percent,

        c.customer_name,

        COALESCE(ci.city_name, '')       AS city_name,
        COALESCE(ar.area_name, '')       AS area_name,
        COALESCE(cz.city_zone_name, '')  AS zone_name,

        COALESCE(b.branch_name, 'No Branch') AS branch_name,

        p.id AS product_id,
        p.code AS product_code,
        p.name AS product_name,

        sii.batch_no,
        sii.batch_expiry_date,

        sii.quantity,
        sii.foc_quantity,

        COALESCE(u.uom_name, '') AS unit_symbol,

        sii.sale_price,
        sii.discount_amount,
        sii.net_amount

    FROM sale_invoice_items sii

    INNER JOIN sale_invoice si
        ON sii.sale_invoice_id = si.id
        AND sii.tenant_id = si.tenant_id

    INNER JOIN products p
        ON sii.product_id = p.id
        AND sii.tenant_id = p.tenant_id

    INNER JOIN customers c
        ON si.customer_id = c.id
        AND c.tenant_id = si.tenant_id

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
        AND b.tenant_id = si.tenant_id

    LEFT JOIN uom u
        ON sii.uom_id = u.id

    WHERE sii.tenant_id = ?
      AND si.status = 'Posted'
      AND p.product_type = 'physical'
";

            $params = [
                $tenant_id
            ];


            // -----------------------------------------------------------------
            // FROM DATE
            // -----------------------------------------------------------------

            if ($from_date !== null) {

                $sql .= "
                    AND si.sale_date >= ?
                ";

                $params[] = $from_date;
            }


            // -----------------------------------------------------------------
            // TO DATE
            //
            // Using < next day makes it work correctly if sale_date is DATETIME.
            //
            // Example:
            // to_date = 2026-08-20
            //
            // will include:
            // 2026-08-20 00:00:00
            // 2026-08-20 10:30:00
            // 2026-08-20 23:59:59
            // -----------------------------------------------------------------

            if ($to_date !== null) {

                try {

                    $next_day = new DateTime($to_date);
                    $next_day->modify('+1 day');

                    $next_day_string =
                        $next_day->format('Y-m-d');

                    $sql .= "
                        AND si.sale_date < ?
                    ";

                    $params[] = $next_day_string;

                } catch (Exception $dateException) {

                    // Fallback if invalid date format.
                    $sql .= "
                        AND si.sale_date <= ?
                    ";

                    $params[] = $to_date;
                }
            }


            // -----------------------------------------------------------------
            // CUSTOMER
            // -----------------------------------------------------------------

            if ($customer_id !== null) {

                $sql .= "
                    AND si.customer_id = ?
                ";

                $params[] = $customer_id;
            }


            // -----------------------------------------------------------------
            // BRANCH
            // -----------------------------------------------------------------

            if ($branch_id !== null) {

                $sql .= "
                    AND si.branch_id = ?
                ";

                $params[] = $branch_id;
            }


            // -----------------------------------------------------------------
            // PRODUCT
            // -----------------------------------------------------------------

            if ($product_id !== null) {

                $sql .= "
                    AND sii.product_id = ?
                ";

                $params[] = $product_id;
            }


            // -----------------------------------------------------------------
            // BATCH NUMBER
            //
            // IMPORTANT:
            // batch_no is directly in sale_invoice_items.
            // -----------------------------------------------------------------

            if ($batch_no !== null && $batch_no !== '') {

                $sql .= "
                    AND sii.batch_no LIKE ?
                ";

                $params[] = '%' . $batch_no . '%';
            }


            // -----------------------------------------------------------------
            // EXPIRY DATE
            //
            // IMPORTANT:
            // batch_expiry_date is directly in sale_invoice_items.
            // -----------------------------------------------------------------

            if ($expiry_date !== null && $expiry_date !== '') {

                $sql .= "
                    AND DATE(sii.batch_expiry_date) = ?
                ";

                $params[] = $expiry_date;
            }


            // -----------------------------------------------------------------
            // SALESMAN (SALE OFFICER)
            //
            // IMPORTANT:
            // sale_officer_id is directly in sale_invoice (si).
            // -----------------------------------------------------------------

            if ($sale_officer_id !== null) {

                $sql .= "
                    AND si.sale_officer_id = ?
                ";

                $params[] = $sale_officer_id;
            }


            // -----------------------------------------------------------------
            // CITY
            //
            // IMPORTANT:
            // city_id is directly in customers (c).
            // -----------------------------------------------------------------

            if ($city_id !== null) {

                $sql .= "
                    AND c.city_id = ?
                ";

                $params[] = $city_id;
            }


            // -----------------------------------------------------------------
            // AREA
            //
            // IMPORTANT:
            // area_id is directly in customers (c).
            // -----------------------------------------------------------------

            if ($area_id !== null) {

                $sql .= "
                    AND c.area_id = ?
                ";

                $params[] = $area_id;
            }


            // -----------------------------------------------------------------
            // ORDER
            // -----------------------------------------------------------------

            $sql .= "
                ORDER BY
                    si.sale_date ASC,
                    si.bill_no ASC,
                    sii.id ASC
            ";


            // -----------------------------------------------------------------
            // EXECUTE
            // -----------------------------------------------------------------

            $stmt = $pdo->prepare($sql);

            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


            // -----------------------------------------------------------------
            // TOTALS
            // -----------------------------------------------------------------

            $total_qty      = 0;
            $total_foc_qty  = 0;
            $total_amount   = 0;
            $total_discount = 0;


            foreach ($rows as &$row) {

                $row['quantity'] =
                    (float)($row['quantity'] ?? 0);

                $row['foc_quantity'] =
                    (float)($row['foc_quantity'] ?? 0);

                $row['sale_price'] =
                    (float)($row['sale_price'] ?? 0);

                $row['discount_amount'] =
                    (float)($row['discount_amount'] ?? 0);

                $row['net_amount'] =
                    (float)($row['net_amount'] ?? 0);
                    $row['invoice_net_amount']   = (float)($row['invoice_net_amount'] ?? 0);
$row['invoice_disc_percent'] = (float)($row['invoice_disc_percent'] ?? 0);


                $total_qty +=
                    $row['quantity'];

                $total_foc_qty +=
                    $row['foc_quantity'];

                $total_amount +=
                    $row['net_amount'];

                $total_discount +=
                    $row['discount_amount'];


                // -------------------------------------------------------------
                // CLEAN EMPTY BATCH
                // -------------------------------------------------------------

                if (
                    !isset($row['batch_no']) ||
                    trim((string)$row['batch_no']) === ''
                ) {

                    $row['batch_no'] = null;
                }


                // -------------------------------------------------------------
                // CLEAN EMPTY EXPIRY
                // -------------------------------------------------------------

                if (
                    !isset($row['batch_expiry_date']) ||
                    trim((string)$row['batch_expiry_date']) === ''
                ) {

                    $row['batch_expiry_date'] = null;
                }


                // -------------------------------------------------------------
                // CLEAN UNIT
                // -------------------------------------------------------------

                if (
                    !isset($row['unit_symbol']) ||
                    $row['unit_symbol'] === null
                ) {

                    $row['unit_symbol'] = '';
                }
            }

            unset($row);


            // -----------------------------------------------------------------
            // RESPONSE
            // -----------------------------------------------------------------

            echo json_encode([
                'success' => true,

                'data' => $rows,

                'currency' => $currency_symbol,

                'filters' => [
                    'from_date'        => $from_date,
                    'to_date'          => $to_date,
                    'customer_id'      => $customer_id,
                    'branch_id'        => $branch_id,
                    'product_id'       => $product_id,
                    'batch_no'         => $batch_no,
                    'expiry_date'      => $expiry_date,
                    'sale_officer_id'  => $sale_officer_id,
                    'city_id'          => $city_id,
                    'area_id'          => $area_id
                ],

                'summary' => [
                    'total_rows'     => count($rows),
                    'total_qty'      => $total_qty,
                    'total_foc_qty'  => $total_foc_qty,
                    'total_discount' => $total_discount,
                    'total_amount'   => $total_amount
                ]
            ]);

            break;


        // =====================================================================
        // CUSTOMERS
        // =====================================================================

        case 'customers':

            $sql = "
                SELECT
                    id,
                    customer_name
                FROM customers
                WHERE tenant_id = ?
                  AND status = 'ACTIVE'
                ORDER BY customer_name ASC
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $tenant_id
            ]);

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;


        // =====================================================================
        // BRANCHES
        // =====================================================================

        case 'branches':

            $sql = "
                SELECT

                    b.id,

                    b.branch_name,

                    b.branch_type,

                    b.parent_branch_id,

                    pb.branch_name AS parent_name

                FROM user_branches ub

                INNER JOIN branches b
                    ON ub.branch_id = b.id
                    AND ub.tenant_id = b.tenant_id

                LEFT JOIN branches pb
                    ON b.parent_branch_id = pb.id
                    AND pb.tenant_id = b.tenant_id

                WHERE ub.tenant_id = ?

                  AND ub.user_id = ?

                  AND b.is_active = 1

                ORDER BY
                    pb.branch_name ASC,
                    b.branch_name ASC
            ";


            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $tenant_id,
                $user_id
            ]);


            $branches =
                $stmt->fetchAll(PDO::FETCH_ASSOC);


            // -----------------------------------------------------------------
            // BRANCH TYPE LABELS
            // -----------------------------------------------------------------

            $type_map = [

                'head_office'
                    => 'Head Office',

                'petrol_station'
                    => 'Petrol Station',

                'convenience_store'
                    => 'Convenience Store',

                'car_wash'
                    => 'Car Wash',

                'fuel_tank'
                    => 'Fuel Tank',

                'warehouse'
                    => 'Warehouse',

                'distribution_center'
                    => 'Distribution Center'
            ];


            foreach ($branches as &$branch) {

                $branch_type =
                    $branch['branch_type'] ?? '';

                $type_display =
                    $type_map[$branch_type]
                    ?? ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $branch_type
                        )
                    );


                $display_name =
                    $branch['branch_name'];


                if ($type_display !== '') {

                    $display_name .=
                        ' (' .
                        $type_display .
                        ')';
                }


                if (
                    !empty(
                        $branch['parent_branch_id']
                    ) &&
                    !empty(
                        $branch['parent_name']
                    )
                ) {

                    $display_name =
                        $branch['parent_name'] .
                        ' > ' .
                        $display_name;
                }


                $branch['display_name'] =
                    $display_name;
            }

            unset($branch);


            echo json_encode([
                'success' => true,
                'data' => $branches
            ]);

            break;


        // =====================================================================
        // PRODUCTS
        // =====================================================================

        case 'products':

            $sql = "
                SELECT

                    id,

                    code,

                    name

                FROM products

                WHERE tenant_id = ?

                  AND is_active = 1

                  AND product_type = 'physical'

                ORDER BY name ASC
            ";


            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $tenant_id
            ]);


            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;


        // =====================================================================
        // SALESMEN (SALE OFFICERS)
        //
        // IMPORTANT:
        // sale_invoice.sale_officer_id references users.id.
        // =====================================================================

        case 'salesmen':

            $sql = "
                SELECT

                    id,

                    full_name

                FROM users

                WHERE tenant_id = ?

                  AND is_active = 1

                ORDER BY full_name ASC
            ";


            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $tenant_id
            ]);


            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;


        // =====================================================================
        // CITIES
        // =====================================================================

        case 'cities':

            $sql = "
                SELECT

                    id,

                    city_name

                FROM cities

                WHERE tenant_id = ?

                ORDER BY city_name ASC
            ";


            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $tenant_id
            ]);


            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;


        // =====================================================================
        // AREAS
        // =====================================================================

        case 'areas':

            $sql = "
                SELECT

                    id,

                    area_name

                FROM areas

                WHERE tenant_id = ?

                ORDER BY area_name ASC
            ";


            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $tenant_id
            ]);


            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;


        // =====================================================================
        // INVALID ACTION
        // =====================================================================

        default:

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid action',
                'allowed_actions' => [
                    'report',
                    'customers',
                    'branches',
                    'products',
                    'salesmen',
                    'cities',
                    'areas'
                ]
            ]);

            break;
    }


} catch (PDOException $e) {

    // =========================================================================
    // DATABASE ERROR
    // =========================================================================

    error_log(
        'Daily Product Sale Report PDO Error: ' .
        $e->getMessage()
    );


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);


} catch (Exception $e) {

    // =========================================================================
    // GENERAL ERROR
    // =========================================================================

    error_log(
        'Daily Product Sale Report Error: ' .
        $e->getMessage()
    );


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
}
