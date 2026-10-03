<?php
session_start();
require_once '../../../../includes/connection.php';
require_once '../../../api/financial_reports/supplier_ledger/currency-converter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $supplier_id = $_GET['supplier_id'] ?? null;
    $project_id  = $_GET['project_id']  ?? null;

    if (!$supplier_id) {
        echo json_encode(['success' => false, 'message' => 'supplier_id required']);
        exit;
    }

    $converter = new CurrencyConverter($pdo);

    // Get base currency
    $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
    $stmt->execute([$tenant_id]);
    $base_currency_id = $stmt->fetchColumn();
    $target_currency_id = $base_currency_id;

    if ($project_id) {
        // ── Project-wise balance ──────────────────────────────────────────────
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM purchase_invoice
            WHERE tenant_id = ? AND supplier_id = ? AND project_id = ?");
        $stmt->execute([$tenant_id, $supplier_id, $project_id]);
        $invoices = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM purchase_return
            WHERE tenant_id = ? AND supplier_id = ? AND project_id = ?");
        $stmt->execute([$tenant_id, $supplier_id, $project_id]);
        $returns = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payment_voucher
            WHERE tenant_id = ? AND supplier_id = ? AND project_id = ?
            AND id NOT IN (
                SELECT reference_id FROM post_dated_cheques
                WHERE tenant_id = ? AND reference_table = 'payment_voucher'
            )");
        $stmt->execute([$tenant_id, $supplier_id, $project_id, $tenant_id]);
        $payments = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(pdc.amount), 0)
            FROM post_dated_cheques pdc
            WHERE pdc.tenant_id = ? AND pdc.supplier_id = ? AND pdc.project_id = ?
            AND pdc.transaction_type = 'Payment' AND pdc.status = 'Approved'");
        $stmt->execute([$tenant_id, $supplier_id, $project_id]);
        $pdcs = (float) $stmt->fetchColumn();

        // Cr (owe to supplier) = invoices | Dr (paid/returned) = payments + pdcs + returns
        // balance positive = Dr (we owe), negative = Cr (supplier owes us)
        $balance = -$invoices + $payments + $pdcs + $returns;

    } else {
        // ── Total balance — same logic as supplier-ledger.php summary ──────────
        $stmt = $pdo->prepare("SELECT opening_debit_amount, opening_credit_amount
            FROM suppliers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$supplier_id, $tenant_id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        $opening_debit  = (float)($supplier['opening_debit_amount']  ?? 0);
        $opening_credit = (float)($supplier['opening_credit_amount'] ?? 0);

        if ($base_currency_id != $target_currency_id) {
            $opening_debit  = $converter->convert($opening_debit,  $base_currency_id, $target_currency_id);
            $opening_credit = $converter->convert($opening_credit, $base_currency_id, $target_currency_id);
        }
        $opening = $opening_debit - $opening_credit;

        // purchase_invoice = Cr (we owe supplier)
        $stmt = $pdo->prepare("SELECT net_amount, currency_id FROM purchase_invoice
            WHERE tenant_id = ? AND supplier_id = ?");
        $stmt->execute([$tenant_id, $supplier_id]);
        $invoices_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_credit = 0;
        foreach ($invoices_raw as $row) {
            $amt = (float)$row['net_amount'];
            if ($row['currency_id'] && $row['currency_id'] != $target_currency_id)
                $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
            $total_credit += $amt;
        }

        // payment_voucher (excluding PDC-linked) = Dr (we paid)
        $stmt = $pdo->prepare("SELECT amount, currency_id FROM payment_voucher
            WHERE tenant_id = ? AND supplier_id = ?
            AND id NOT IN (
                SELECT reference_id FROM post_dated_cheques
                WHERE tenant_id = ? AND reference_table = 'payment_voucher'
            )");
        $stmt->execute([$tenant_id, $supplier_id, $tenant_id]);
        $payments_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_debit = 0;
        foreach ($payments_raw as $row) {
            $amt = (float)$row['amount'];
            if ($row['currency_id'] && $row['currency_id'] != $target_currency_id)
                $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
            $total_debit += $amt;
        }

        // Approved PDCs = Dr
        $stmt = $pdo->prepare("SELECT pdc.amount, pv.currency_id
            FROM post_dated_cheques pdc
            LEFT JOIN payment_voucher pv ON pdc.reference_id = pv.id AND pdc.reference_table = 'payment_voucher'
            WHERE pdc.tenant_id = ? AND pdc.supplier_id = ?
            AND pdc.transaction_type = 'Payment' AND pdc.status = 'Approved'");
        $stmt->execute([$tenant_id, $supplier_id]);
        $pdcs_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pdcs_raw as $row) {
            $amt = (float)$row['amount'];
            if ($row['currency_id'] && $row['currency_id'] != $target_currency_id)
                $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
            $total_debit += $amt;
        }

        // purchase_return = Dr (reduces what we owe)
        $stmt = $pdo->prepare("SELECT net_amount, currency_id FROM purchase_return
            WHERE tenant_id = ? AND supplier_id = ?");
        $stmt->execute([$tenant_id, $supplier_id]);
        $returns_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($returns_raw as $row) {
            $amt = (float)$row['net_amount'];
            if ($row['currency_id'] && $row['currency_id'] != $target_currency_id)
                $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
            $total_debit += $amt;
        }

        // closing_balance = opening - credit + debit  (same as supplier-ledger.php)
        $balance = $opening - $total_credit + $total_debit;
    }

    echo json_encode([
        'success'    => true,
        'balance'    => $balance,
        'project_id' => $project_id,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
