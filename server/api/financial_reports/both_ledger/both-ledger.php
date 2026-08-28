<?php
// Combined Customer+Supplier ("Both") ledger. This deliberately does NOT
// re-implement the customer-ledger.php / supplier-ledger.php business logic
// (currency conversion, PDC handling, transfers, journal adjustments, etc.) -
// instead it calls those two existing, unmodified endpoints internally and
// merges their already-correct output. This guarantees the combined ledger's
// numbers always match the individual ledgers exactly, and the individual
// ledger files stay completely untouched.
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

// Build the cookie header needed to authenticate the internal sub-requests as
// this same user, then release the session lock immediately so those
// sub-requests (which also call session_start()) don't block on it.
$cookieName = session_name();
$cookieValue = session_id();
session_write_close();

// Always target the loopback address for these internal sub-requests, never
// $_SERVER['HTTP_HOST'] (client-supplied and spoofable) - otherwise a forged
// Host header could redirect this server-to-server call, and the session
// cookie it carries, to an attacker-controlled host.
$scheme = 'http';
$host = '127.0.0.1';
$financialReportsDir = dirname(dirname($_SERVER['SCRIPT_NAME'])); // .../server/api/financial_reports

function fetchInternalLedger($url, $cookieHeader) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Cookie: $cookieHeader\r\n",
            'timeout' => 20,
            'ignore_errors' => true
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return null;
    return json_decode($response, true);
}

$cookieHeader = "$cookieName=$cookieValue";

try {
    $type = $_GET['type'] ?? 'summary';
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';
    $company_id = $_GET['company_id'] ?? '';
    $target_currency_id = $_GET['currency_id'] ?? '';

    // List of "Both" parties, for the dropdown
    if ($type === 'parties') {
        $stmt = $pdo->prepare("SELECT id, customer_code, customer_name, linked_supplier_id FROM customers WHERE tenant_id = ? AND is_both = 1 AND status = 'ACTIVE' ORDER BY customer_name");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($type === 'currencies') {
        $stmt = $pdo->prepare("SELECT tc.currency_id as id, c.name, c.symbol, c.code, tc.is_base_currency FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_active = 1 ORDER BY tc.is_base_currency DESC, c.name");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    $customer_id = $_GET['customer_id'] ?? null;

    if ($type === 'summary') {
        // One row per "Both" party, showing both sides' opening/debit/credit/closing
        $stmt = $pdo->prepare("SELECT id, customer_name, linked_supplier_id FROM customers WHERE tenant_id = ? AND is_both = 1 AND status = 'ACTIVE'" . ($customer_id ? " AND id = ?" : "") . " ORDER BY customer_name");
        $params = [$tenant_id];
        if ($customer_id) $params[] = $customer_id;
        $stmt->execute($params);
        $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($parties as $party) {
            $custParams = ['type' => 'summary', 'customer_id' => $party['id']];
            if ($from_date) $custParams['from_date'] = $from_date;
            if ($to_date) $custParams['to_date'] = $to_date;
            if ($company_id) $custParams['company_id'] = $company_id;
            if ($target_currency_id) $custParams['currency_id'] = $target_currency_id;
            $custUrl = "$scheme://$host$financialReportsDir/customer_ledger/customer-ledger.php?" . http_build_query($custParams);
            $custResp = fetchInternalLedger($custUrl, $cookieHeader);
            $custRow = ($custResp && $custResp['success'] && !empty($custResp['data'])) ? $custResp['data'][0] : null;

            $suppRow = null;
            if (!empty($party['linked_supplier_id'])) {
                $suppParams = ['type' => 'summary', 'supplier_id' => $party['linked_supplier_id']];
                if ($from_date) $suppParams['from_date'] = $from_date;
                if ($to_date) $suppParams['to_date'] = $to_date;
                if ($company_id) $suppParams['company_id'] = $company_id;
                if ($target_currency_id) $suppParams['currency_id'] = $target_currency_id;
                $suppUrl = "$scheme://$host$financialReportsDir/supplier_ledger/supplier-ledger.php?" . http_build_query($suppParams);
                $suppResp = fetchInternalLedger($suppUrl, $cookieHeader);
                $suppRow = ($suppResp && $suppResp['success'] && !empty($suppResp['data'])) ? $suppResp['data'][0] : null;
            }

            $result[] = [
                'customer_id' => $party['id'],
                'supplier_id' => $party['linked_supplier_id'],
                'party_name' => $party['customer_name'],
                'customer_opening_balance' => $custRow['opening_balance'] ?? 0,
                'customer_total_debit' => $custRow['total_debit'] ?? 0,
                'customer_total_credit' => $custRow['total_credit'] ?? 0,
                'customer_closing_balance' => $custRow['closing_balance'] ?? 0,
                'supplier_opening_balance' => $suppRow['opening_balance'] ?? 0,
                'supplier_total_debit' => $suppRow['total_debit'] ?? 0,
                'supplier_total_credit' => $suppRow['total_credit'] ?? 0,
                'supplier_closing_balance' => $suppRow['closing_balance'] ?? 0,
            ];
        }

        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    }

    // Detailed
    if (!$customer_id) {
        echo json_encode(['success' => false, 'message' => 'Party (customer_id) is required for detailed ledger']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, customer_name, linked_supplier_id FROM customers WHERE id = ? AND tenant_id = ? AND is_both = 1");
    $stmt->execute([$customer_id, $tenant_id]);
    $party = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$party) {
        echo json_encode(['success' => false, 'message' => 'Both (Customer + Supplier) record not found']);
        exit;
    }
    $supplier_id = $party['linked_supplier_id'];

    $custParams = ['type' => 'detailed', 'customer_id' => $customer_id];
    if ($from_date) $custParams['from_date'] = $from_date;
    if ($to_date) $custParams['to_date'] = $to_date;
    if ($company_id) $custParams['company_id'] = $company_id;
    if ($target_currency_id) $custParams['currency_id'] = $target_currency_id;
    $custUrl = "$scheme://$host$financialReportsDir/customer_ledger/customer-ledger.php?" . http_build_query($custParams);
    $custResp = fetchInternalLedger($custUrl, $cookieHeader);

    $suppResp = null;
    if ($supplier_id) {
        $suppParams = ['type' => 'detailed', 'supplier_id' => $supplier_id];
        if ($from_date) $suppParams['from_date'] = $from_date;
        if ($to_date) $suppParams['to_date'] = $to_date;
        if ($company_id) $suppParams['company_id'] = $company_id;
        if ($target_currency_id) $suppParams['currency_id'] = $target_currency_id;
        $suppUrl = "$scheme://$host$financialReportsDir/supplier_ledger/supplier-ledger.php?" . http_build_query($suppParams);
        $suppResp = fetchInternalLedger($suppUrl, $cookieHeader);
    }

    if (!$custResp || !$custResp['success']) {
        echo json_encode(['success' => false, 'message' => 'Failed to load customer-side ledger: ' . ($custResp['message'] ?? 'unknown error')]);
        exit;
    }
    if ($supplier_id && (!$suppResp || !$suppResp['success'])) {
        echo json_encode(['success' => false, 'message' => 'Failed to load supplier-side ledger: ' . ($suppResp['message'] ?? 'unknown error')]);
        exit;
    }

    // Map customer-ledger's internal "type" markers to a human transaction-type label.
    $customerTypeLabels = [
        'invoice' => 'Sale',
        'return' => 'Sale Return',
        'payment' => 'Receive',
        'adjustment_rv' => 'Adjustment',
        'rent' => 'Rent',
        'transfer_out' => 'Transfer Out',
        'transfer_in' => 'Transfer In',
        'adjustment' => 'Adjustment',
        'payment_voucher' => 'Payment',
        'pdc' => 'PDC'
    ];

    $openingRows = [];
    $datedRows = [];

    foreach (($custResp['data'] ?? []) as $row) {
        $rowType = $row['type'] ?? '';
        if ($rowType === 'sub_account_header' || $rowType === 'sub_account_total') {
            continue; // visual grouping markers only, not real transactions
        }
        $normalized = [
            'date' => $row['date'],
            'description' => $row['description'] ?? '',
            'reference' => $row['reference'] ?? '',
            'debit' => floatval($row['debit'] ?? 0),
            'credit' => floatval($row['credit'] ?? 0),
            'side' => 'customer',
            'txn_type' => $rowType === 'opening_balance' ? 'Opening (Customer)' : ($customerTypeLabels[$rowType] ?? ucfirst($rowType ?: 'Other')),
            'customer_running_balance' => isset($row['running_balance']) ? floatval($row['running_balance']) : null,
            'supplier_running_balance' => null
        ];
        if ($rowType === 'opening_balance') {
            $openingRows[] = $normalized;
        } else {
            $datedRows[] = $normalized;
        }
    }

    if ($supplier_id && $suppResp) {
        // supplier-ledger groups rows by sub_account_id; flatten every group.
        $suppRowsFlat = [];
        foreach (($suppResp['data'] ?? []) as $group) {
            foreach ($group as $r) {
                $suppRowsFlat[] = $r;
            }
        }

        $supplierOpening = floatval($suppResp['opening_balance'] ?? 0);
        $openingRows[] = [
            'date' => $from_date ?: 'Opening Balance',
            'description' => 'Opening Balance',
            'reference' => 'OB-SUPP',
            'debit' => 0,
            'credit' => 0,
            'side' => 'supplier',
            'txn_type' => 'Opening (Supplier)',
            'customer_running_balance' => null,
            'supplier_running_balance' => $supplierOpening
        ];

        foreach ($suppRowsFlat as $row) {
            $desc = $row['description'] ?? '';
            if (($row['type'] ?? '') === 'adjustment' || stripos($desc, 'Adjustment -') === 0) {
                $label = 'Adjustment';
            } elseif (stripos($desc, 'Purchase Invoice -') === 0) {
                $label = 'Purchase';
            } elseif (stripos($desc, 'Purchase Return -') === 0) {
                $label = 'Purchase Return';
            } elseif (stripos($desc, 'PDC -') === 0) {
                $label = 'PDC';
            } elseif (stripos($desc, 'Transfer Out -') === 0) {
                $label = 'Transfer Out';
            } elseif (stripos($desc, 'Transfer In -') === 0) {
                $label = 'Transfer In';
            } elseif (stripos($desc, 'Expense Voucher -') === 0) {
                $label = 'Expense';
            } elseif (stripos($desc, 'Payment -') === 0) {
                $label = 'Payment';
            } else {
                $label = 'Other';
            }

            $debit = floatval($row['debit'] ?? 0);
            if (isset($row['pdc_status']) && $row['pdc_status'] !== 'Approved') {
                $debit = 0; // matches supplier-ledger.php's own running-balance rule
            }

            $datedRows[] = [
                'date' => $row['date'],
                'description' => $desc,
                'reference' => $row['reference'] ?? '',
                'debit' => $debit,
                'credit' => floatval($row['credit'] ?? 0),
                'side' => 'supplier',
                'txn_type' => $label,
                'customer_running_balance' => null,
                'supplier_running_balance' => isset($row['running_balance']) ? floatval($row['running_balance']) : null
            ];
        }
    }

    // Sort dated rows chronologically; opening rows always come first.
    usort($datedRows, function ($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });

    $merged = array_merge($openingRows, $datedRows);

    // Carry forward each side's running balance onto rows belonging to the other side,
    // so both balance columns are always populated in the combined table.
    $lastCustomerBalance = 0;
    $lastSupplierBalance = 0;
    foreach ($merged as &$row) {
        if ($row['customer_running_balance'] !== null) {
            $lastCustomerBalance = $row['customer_running_balance'];
        } else {
            $row['customer_running_balance'] = $lastCustomerBalance;
        }
        if ($row['supplier_running_balance'] !== null) {
            $lastSupplierBalance = $row['supplier_running_balance'];
        } else {
            $row['supplier_running_balance'] = $lastSupplierBalance;
        }
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'party_name' => $party['customer_name'],
        'customer_id' => $customer_id,
        'supplier_id' => $supplier_id,
        'data' => $merged,
        'customer_opening_balance' => $custResp['opening_balance'] ?? 0,
        'supplier_opening_balance' => $supplier_id ? ($suppResp['opening_balance'] ?? 0) : 0
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
