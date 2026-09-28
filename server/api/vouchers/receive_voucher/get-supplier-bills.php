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

$supplier_code = $_GET['supplier_code'] ?? '';

if (!$supplier_code) {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id FROM suppliers
        WHERE tenant_id = ? AND supplier_code = ?
    ");
    $stmt->execute([$tenant_id, $supplier_code]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT pi.id, pi.bill_no, pi.net_amount, pi.purchase_date as sale_date, c.symbol as currency_symbol
        FROM purchase_invoice pi
        LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
        WHERE pi.tenant_id = ? AND pi.supplier_id = ?
    ");
    $stmt->execute([$tenant_id, $supplier['id']]);
    $allBills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    usort($allBills, function($a, $b) {
        return strtotime($b['sale_date']) - strtotime($a['sale_date']);
    });

    // Recompute each bill's amount as the REMAINING balance (net - returned - already received against it)
    if (!empty($allBills)) {
        $billNos = array_column($allBills, 'bill_no');
        $placeholders = implode(',', array_fill(0, count($billNos), '?'));

        $stmt = $pdo->prepare("
            SELECT bill_no, SUM(amount + adjust_amount) as total_received
            FROM receive_voucher
            WHERE tenant_id = ? AND supplier_id = ? AND bill_no IN ($placeholders)
            GROUP BY bill_no
        ");
        $stmt->execute(array_merge([$tenant_id, $supplier['id']], $billNos));
        $receivedByBill = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $receivedByBill[$row['bill_no']] = (float) $row['total_received'];
        }

        // purchase_return carries its own bill_no, so returns can be matched by bill_no directly
        $stmt = $pdo->prepare("
            SELECT bill_no, SUM(net_amount) as total_returned
            FROM purchase_return
            WHERE tenant_id = ? AND supplier_id = ? AND bill_no IN ($placeholders)
            GROUP BY bill_no
        ");
        $stmt->execute(array_merge([$tenant_id, $supplier['id']], $billNos));
        $returnedByBillNo = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $returnedByBillNo[$row['bill_no']] = (float) $row['total_returned'];
        }

        $allBills = array_values(array_filter(array_map(function ($bill) use ($receivedByBill, $returnedByBillNo) {
            $returned = $returnedByBillNo[$bill['bill_no']] ?? 0;
            $netOwed = (float) $bill['net_amount'] - $returned;
            $received = $receivedByBill[$bill['bill_no']] ?? 0;
            $remaining = $netOwed - $received;

            $bill['net_amount'] = round($remaining, 2);
            unset($bill['id']);

            return $bill;
        }, $allBills), function ($bill) {
            return $bill['net_amount'] > 0.01;
        }));
    }

    echo json_encode(['success' => true, 'data' => $allBills]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
