<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$customer_code = $_GET['customer_code'] ?? '';

if (!$customer_code) {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

try {
    // First get customer ID from customer_code
    $stmt = $pdo->prepare("
        SELECT id FROM customers
        WHERE tenant_id = ? AND customer_code = ?
    ");
    $stmt->execute([$tenant_id, $customer_code]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }

    // Get bills from sale_invoice (also grab si.id — needed to match sale_return.sale_invoice_no below)
    $stmt = $pdo->prepare("
        SELECT si.id, si.bill_no, si.net_amount, si.amount_returned, si.sale_date, c.symbol as currency_symbol
        FROM sale_invoice si
        LEFT JOIN ledgerone_public.currencies c ON si.currency_id = c.id
        WHERE si.tenant_id = ? AND si.customer_id = ?
    ");
    $stmt->execute([$tenant_id, $customer['id']]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get bills from opening_balance_invoices (no sale_return linkage exists for these — see note below)
    $stmt = $pdo->prepare("
        SELECT NULL as id, obi.invoice_number as bill_no, obi.debit as net_amount, 0 as amount_returned, obi.invoice_date as sale_date, c.symbol as currency_symbol
        FROM opening_balance_invoices obi
        LEFT JOIN tenant_currencies tc ON obi.tenant_id = tc.tenant_id AND tc.is_base_currency = 1
        LEFT JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
        WHERE obi.tenant_id = ? AND obi.customer_id = ?
    ");
    $stmt->execute([$tenant_id, $customer['id']]);
    $openingBills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge both arrays
    $allBills = array_merge($bills, $openingBills);

    // Sort by date descending
    usort($allBills, function($a, $b) {
        return strtotime($b['sale_date']) - strtotime($a['sale_date']);
    });

    // Recompute each bill's amount as the REMAINING balance (net - returned - already received/adjusted),
    // then drop bills that are fully settled.
    if (!empty($allBills)) {
        $billNos = array_column($allBills, 'bill_no');
        $placeholders = implode(',', array_fill(0, count($billNos), '?'));

        $stmt = $pdo->prepare("
            SELECT bill_no, SUM(amount + adjust_amount) as total_received
            FROM receive_voucher
            WHERE tenant_id = ? AND customer_id = ? AND bill_no IN ($placeholders)
            GROUP BY bill_no
        ");
        $stmt->execute(array_merge([$tenant_id, $customer['id']], $billNos));
        $receivedByBill = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $receivedByBill[$row['bill_no']] = (float) $row['total_received'];
        }

        // Pull posted sale returns directly from sale_return, summed per original invoice.
        // sale_invoice.amount_returned is never updated anywhere in the app, so it can't be
        // trusted — sale_return is the live source of truth for how much was returned.
        $invoiceIds = array_values(array_filter(array_column($allBills, 'id')));
        $returnedByInvoiceId = [];
        if (!empty($invoiceIds)) {
            $idPlaceholders = implode(',', array_fill(0, count($invoiceIds), '?'));
            $stmt = $pdo->prepare("
                SELECT sale_invoice_no, SUM(net_amount) as total_returned
                FROM sale_return
                WHERE tenant_id = ? AND customer_id = ? AND status = 'Posted'
                  AND sale_invoice_no IN ($idPlaceholders)
                GROUP BY sale_invoice_no
            ");
            $stmt->execute(array_merge([$tenant_id, $customer['id']], $invoiceIds));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $returnedByInvoiceId[$row['sale_invoice_no']] = (float) $row['total_returned'];
            }
        }

        $allBills = array_values(array_filter(array_map(function ($bill) use ($receivedByBill, $returnedByInvoiceId) {
            $returned = $bill['id'] ? ($returnedByInvoiceId[$bill['id']] ?? 0) : 0;
            $netOwed = (float) $bill['net_amount'] - $returned;
            $received = $receivedByBill[$bill['bill_no']] ?? 0;
            $remaining = $netOwed - $received;

            // Frontend reads `net_amount` directly as the bill's amount (see receive-add.js:
            // bills = data.data.map(bill => ({ amount: bill.net_amount, ... })))
            // so this must carry the REMAINING balance, not the original invoice total.
            $bill['net_amount']     = round($remaining, 2);
            $bill['amount_returned'] = round($returned, 2); // now reflects live sale_return data
            $bill['already_received'] = round($received, 2); // optional, for debugging/UI if needed
            unset($bill['id']);

            return $bill;
        }, $allBills), function ($bill) {
            // 0.01 tolerance for float rounding
            return $bill['net_amount'] > 0.01;
        }));
    }

    echo json_encode(['success' => true, 'data' => $allBills]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}