<?php
/**
 * Customer Statement Report - API (FIXED v2)
 * ---------------------------------------------------------------
 *
 * FIX 1: Customers with opening balance but NO transactions 
 *        ab appear karengi WITH FROM_DATE
 * FIX 2: Proper totals calculation opening balance + sale invoice
 * FIX 3: Empty date field ab bhara hua hai from_date ke sath
 *
 * LOGIC:
 * Opening Balance
 *     = Static Opening
 *     + Sale Invoices before From Date
 *     - Receive Vouchers before From Date
 *     - Sale Returns before From Date
 *
 * Closing Balance
 *     = Opening
 *     + Sale Invoice
 *     - Receive Voucher
 *     - Sale Return
 */

require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {

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

    $action = $_GET['action'] ?? 'statement';

    switch ($action) {

        case 'customers':
            $sql = "
                SELECT
                    id,
                    customer_name
                FROM customers
                WHERE tenant_id = ?
                  AND status = 'ACTIVE'
                ORDER BY customer_name
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $customers
            ]);
            break;

        case 'cities':
            $sql = "
                SELECT 
                    id, 
                    city_name AS name
                FROM cities
                WHERE tenant_id = ?
                ORDER BY city_name
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $cities
            ]);
            break;

        case 'zones':
            $city_id = !empty($_GET['city_id']) ? (int)$_GET['city_id'] : null;

            if (!$city_id) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }

            $sql = "
                SELECT 
                    id, 
                    city_zone_name AS name
                FROM city_zones
                WHERE tenant_id = ?
                  AND city_id = ?
                ORDER BY city_zone_name
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $city_id]);
            $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $zones
            ]);
            break;

        case 'areas':
            $city_zone_id = !empty($_GET['city_zone_id'])
                ? (int)$_GET['city_zone_id']
                : null;

            if (!$city_zone_id) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }

            $sql = "
                SELECT 
                    id, 
                    area_name AS name
                FROM areas
                WHERE tenant_id = ?
                  AND city_zone_id = ?
                ORDER BY area_name
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $city_zone_id]);
            $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $areas
            ]);
            break;

        case 'statement':

            $customer_id = isset($_GET['customer_id']) && $_GET['customer_id'] !== ''
                ? (int)$_GET['customer_id']
                : null;

            $from_date = !empty($_GET['from_date']) ? $_GET['from_date'] : null;
            $to_date   = !empty($_GET['to_date'])   ? $_GET['to_date']   : null;

            $city_id      = !empty($_GET['city_id'])      ? (int)$_GET['city_id']      : null;
            $city_zone_id = !empty($_GET['city_zone_id']) ? (int)$_GET['city_zone_id'] : null;
            $area_id      = !empty($_GET['area_id'])      ? (int)$_GET['area_id']      : null;

            if ($from_date && $to_date && $from_date > $to_date) {
                echo json_encode([
                    'success' => false,
                    'message' => 'From Date cannot be greater than To Date.'
                ]);
                exit;
            }

            // Get Customers
            $params = [$tenant_id];
            $where  = "tenant_id = ? AND status = 'ACTIVE'";

            if ($customer_id) {
                $where   .= " AND id = ?";
                $params[] = $customer_id;
            }

            if ($city_id) {
                $where   .= " AND city_id = ?";
                $params[] = $city_id;
            }

            if ($city_zone_id) {
                $where   .= " AND city_zone_id = ?";
                $params[] = $city_zone_id;
            }

            if ($area_id) {
                $where   .= " AND area_id = ?";
                $params[] = $area_id;
            }

            $cust_sql = "
                SELECT
                    id,
                    customer_name,
                    opening_debit_amount,
                    opening_credit_amount
                FROM customers
                WHERE {$where}
                ORDER BY customer_name
            ";

            $cust_stmt = $pdo->prepare($cust_sql);
            $cust_stmt->execute($params);
            $customers = $cust_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$customers) {
                echo json_encode([
                    'success'         => true,
                    'currency'        => $currency_symbol,
                    'customer_name'   => 'All Customers',
                    'from_date'       => $from_date,
                    'to_date'         => $to_date,
                    'opening_balance' => 0,
                    'closing_balance' => 0,
                    'total_sale_invoice' => 0,
                    'total_receive_voucher' => 0,
                    'total_sale_return' => 0,
                    'rows'            => []
                ]);
                exit;
            }

            $all_rows = [];
            $total_opening_balance = 0;
            $total_closing_balance = 0;
            $total_sale_invoice = 0;
            $total_receive_voucher = 0;
            $total_sale_return = 0;

            foreach ($customers as $customer) {

                $cid           = (int)$customer['id'];
                $customer_name = $customer['customer_name'];

                $static_opening =
                    (float)$customer['opening_debit_amount']
                    -
                    (float)$customer['opening_credit_amount'];

                $bbf = $static_opening;

                if ($from_date) {
                    $si_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(net_amount), 0)
                        FROM sale_invoice
                        WHERE tenant_id = ?
                          AND customer_id = ?
                          AND status = 'Posted'
                          AND sale_date < ?
                    ");
                    $si_stmt->execute([$tenant_id, $cid, $from_date]);
                    $bbf += (float)$si_stmt->fetchColumn();

                    $rv_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(amount), 0)
                        FROM receive_voucher
                        WHERE tenant_id = ?
                          AND customer_id = ?
                          AND voucher_date < ?
                    ");
                    $rv_stmt->execute([$tenant_id, $cid, $from_date]);
                    $bbf -= (float)$rv_stmt->fetchColumn();

                    $sr_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(net_amount), 0)
                        FROM sale_return
                        WHERE tenant_id = ?
                          AND customer_id = ?
                          AND status = 'Posted'
                          AND sale_date < ?
                    ");
                    $sr_stmt->execute([$tenant_id, $cid, $from_date]);
                    $bbf -= (float)$sr_stmt->fetchColumn();
                }

                $total_opening_balance += $bbf;

                $days = [];

                $makeDay = function ($date) use (&$days) {
                    if (!isset($days[$date])) {
                        $days[$date] = [
                            'sale_invoice'    => 0,
                            'receive_voucher' => 0,
                            'sale_return'     => 0,
                            'si_bills'        => [],
                            'rv_bills'        => [],
                            'sr_bills'        => []
                        ];
                    }
                };

                // SALE INVOICES
                $si_sql = "
                    SELECT sale_date, bill_no, net_amount
                    FROM sale_invoice
                    WHERE tenant_id = ?
                      AND customer_id = ?
                      AND status = 'Posted'
                ";
                $si_params = [$tenant_id, $cid];

                if ($from_date) {
                    $si_sql .= " AND sale_date >= ?";
                    $si_params[] = $from_date;
                }
                if ($to_date) {
                    $si_sql .= " AND sale_date <= ?";
                    $si_params[] = $to_date;
                }

                $si_sql .= " ORDER BY sale_date";

                $si_stmt = $pdo->prepare($si_sql);
                $si_stmt->execute($si_params);

                foreach ($si_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $d = $row['sale_date'];
                    // Skip if date is null/empty
                    if (!$d) continue;
                    $makeDay($d);
                    $days[$d]['sale_invoice'] += (float)$row['net_amount'];
                    if (isset($row['bill_no']) && $row['bill_no'] !== '') {
                        $days[$d]['si_bills'][] = $row['bill_no'];
                    }
                }

                // RECEIVE VOUCHERS
                $rv_sql = "
                    SELECT voucher_date, voucher_number, amount
                    FROM receive_voucher
                    WHERE tenant_id = ?
                      AND customer_id = ?
                ";
                $rv_params = [$tenant_id, $cid];

                if ($from_date) {
                    $rv_sql .= " AND voucher_date >= ?";
                    $rv_params[] = $from_date;
                }
                if ($to_date) {
                    $rv_sql .= " AND voucher_date <= ?";
                    $rv_params[] = $to_date;
                }

                $rv_sql .= " ORDER BY voucher_date";

                $rv_stmt = $pdo->prepare($rv_sql);
                $rv_stmt->execute($rv_params);

                foreach ($rv_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $d = $row['voucher_date'];
                    // Skip if date is null/empty
                    if (!$d) continue;
                    $makeDay($d);
                    $days[$d]['receive_voucher'] += (float)$row['amount'];
                    if (isset($row['voucher_number']) && $row['voucher_number'] !== '') {
                        $days[$d]['rv_bills'][] = $row['voucher_number'];
                    }
                }

                // SALE RETURNS
                $sr_sql = "
                    SELECT sale_date, bill_no, net_amount
                    FROM sale_return
                    WHERE tenant_id = ?
                      AND customer_id = ?
                      AND status = 'Posted'
                ";
                $sr_params = [$tenant_id, $cid];

                if ($from_date) {
                    $sr_sql .= " AND sale_date >= ?";
                    $sr_params[] = $from_date;
                }
                if ($to_date) {
                    $sr_sql .= " AND sale_date <= ?";
                    $sr_params[] = $to_date;
                }

                $sr_sql .= " ORDER BY sale_date";

                $sr_stmt = $pdo->prepare($sr_sql);
                $sr_stmt->execute($sr_params);

                foreach ($sr_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $d = $row['sale_date'];
                    // Skip if date is null/empty
                    if (!$d) continue;
                    $makeDay($d);
                    $days[$d]['sale_return'] += (float)$row['net_amount'];
                    if (isset($row['bill_no']) && $row['bill_no'] !== '') {
                        $days[$d]['sr_bills'][] = $row['bill_no'];
                    }
                }

                ksort($days);

                $running = $bbf;
                $customer_has_transactions = false;

                foreach ($days as $date => $d) {

                    $opening = $running;
                    $closing = $opening
                        + $d['sale_invoice']
                        - $d['receive_voucher']
                        - $d['sale_return'];

                    $running = $closing;
                    $customer_has_transactions = true;

                    $all_rows[] = [
                        'date'             => $date,
                        'customer_id'      => $cid,
                        'customer_name'    => $customer_name,
                        'opening_balance'  => round($opening, 2),
                        'sale_invoice'     => round($d['sale_invoice'], 2),
                        'receive_voucher'  => round($d['receive_voucher'], 2),
                        'sale_return'      => round($d['sale_return'], 2),
                        'closing_balance'  => round($closing, 2),
                        'invoice_nos'      => implode(', ', array_unique(array_filter($d['si_bills']))),
                        'voucher_nos'      => implode(', ', array_unique(array_filter($d['rv_bills']))),
                        'return_nos'       => implode(', ', array_unique(array_filter($d['sr_bills'])))
                    ];

                    // Add to totals
                    $total_sale_invoice += (float)$d['sale_invoice'];
                    $total_receive_voucher += (float)$d['receive_voucher'];
                    $total_sale_return += (float)$d['sale_return'];
                }

                // FIX 3: Date ab bhara hua hai!
                // Agar customer ke paas opening balance hai but koi transaction nahi
                // to ab from_date show hoga (ya aaj ki date agar from_date nahi hai)
                if (!$customer_has_transactions && $bbf != 0) {
                    $display_date = $from_date ?? date('Y-m-d');
                    
                    $all_rows[] = [
                        'date'             => $display_date,  // <-- FIX: Ab bhara hua hai!
                        'customer_id'      => $cid,
                        'customer_name'    => $customer_name,
                        'opening_balance'  => round($bbf, 2),
                        'sale_invoice'     => 0,
                        'receive_voucher'  => 0,
                        'sale_return'      => 0,
                        'closing_balance'  => round($bbf, 2),
                        'invoice_nos'      => '',
                        'voucher_nos'      => '',
                        'return_nos'       => ''
                    ];
                }

                $total_closing_balance = $running;
            }

            // Sort by Date then Customer
            usort($all_rows, function ($a, $b) {
                $dateCompare = strcmp($a['date'], $b['date']);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                return strcasecmp($a['customer_name'], $b['customer_name']);
            });

            if ($customer_id) {
                $response_customer_name = $customers[0]['customer_name'];
            } else {
                $response_customer_name = 'All Customers';
            }

            // FIX 2: Proper totals ab return ho rahe hain
            echo json_encode([
                'success'                => true,
                'currency'               => $currency_symbol,
                'customer_name'          => $response_customer_name,
                'customer_id'            => $customer_id,
                'from_date'              => $from_date,
                'to_date'                => $to_date,
                'customer_count'         => count($customers),
                'opening_balance'        => round($total_opening_balance, 2),
                'closing_balance'        => round($total_closing_balance, 2),
                'total_sale_invoice'     => round($total_sale_invoice, 2),
                'total_receive_voucher'  => round($total_receive_voucher, 2),
                'total_sale_return'      => round($total_sale_return, 2),
                'rows'                   => $all_rows
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log('Customer Statement API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>