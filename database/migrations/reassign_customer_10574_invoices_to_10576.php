<?php
/**
 * Migration: Reassign all transaction records from customer_id 10574 → 10576 for tenant 40.
 *
 * Both customers are named "Rahman Enterprises" — 10574 is the duplicate entry.
 * Covers: sale_invoice, sale_order, receive_voucher, sale_return, payment_voucher,
 *         transfer_voucher (from_customer_id + to_customer_id).
 *
 * Usage:
 *   DRY RUN (default, safe to run anytime):
 *     http://yourdomain/ledgerone_erp/database/migrations/reassign_customer_10574_invoices_to_10576.php
 *
 *   LIVE RUN (writes to DB — wrapped in transaction, rolls back on any error):
 *     http://yourdomain/ledgerone_erp/database/migrations/reassign_customer_10574_invoices_to_10576.php?run=1
 */

require_once __DIR__ . '/../../includes/connection.php';

header('Content-Type: text/plain; charset=utf-8');

const TENANT_ID     = 40;
const FROM_CUSTOMER = 10574;
const TO_CUSTOMER   = 10576;

$dry_run = !(isset($_GET['run']) && $_GET['run'] === '1');

// ── Pre-flight: verify both customers exist under this tenant ────────────────
echo "=== PRE-FLIGHT VERIFICATION ===\n\n";

$stmt = $pdo->prepare(
    "SELECT id, customer_name FROM customers WHERE tenant_id = ? AND id IN (?, ?) ORDER BY id"
);
$stmt->execute([TENANT_ID, FROM_CUSTOMER, TO_CUSTOMER]);
$found     = $stmt->fetchAll(PDO::FETCH_ASSOC);
$found_ids = array_column($found, 'id');

$abort = false;
foreach ([FROM_CUSTOMER, TO_CUSTOMER] as $cid) {
    if (!in_array($cid, $found_ids)) {
        echo "FAIL: customer_id $cid not found in customers table for tenant " . TENANT_ID . "\n";
        $abort = true;
    }
}

if (!$abort) {
    foreach ($found as $row) {
        $label = $row['id'] == FROM_CUSTOMER ? 'SOURCE (will be emptied)' : 'TARGET (will receive records)';
        echo "OK  : customer_id {$row['id']}  [{$row['customer_name']}]  — $label\n";
    }
}

if ($abort) {
    echo "\nAborted: pre-flight failed.\n";
    exit(1);
}

// ── Tables to update ─────────────────────────────────────────────────────────
// Each entry: [table, column]. transfer_voucher has two customer columns.
$targets = [
    ['table' => 'sale_invoice',    'column' => 'customer_id'],
    ['table' => 'sale_order',      'column' => 'customer_id'],
    ['table' => 'sale_return',     'column' => 'customer_id'],
    ['table' => 'receive_voucher', 'column' => 'customer_id'],
    ['table' => 'payment_voucher', 'column' => 'customer_id'],
    ['table' => 'transfer_voucher','column' => 'from_customer_id'],
    ['table' => 'transfer_voucher','column' => 'to_customer_id'],
];

// ── Count affected rows ──────────────────────────────────────────────────────
echo "\n=== AFFECTED ROW COUNTS ===\n\n";

$grand_total = 0;
foreach ($targets as $t) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `{$t['table']}` WHERE tenant_id = ? AND `{$t['column']}` = ?"
    );
    $stmt->execute([TENANT_ID, FROM_CUSTOMER]);
    $cnt = (int)$stmt->fetchColumn();
    $grand_total += $cnt;
    echo sprintf("  %-40s %4d row(s)\n", "{$t['table']}.{$t['column']}", $cnt);
}

echo "\n  Total: $grand_total row(s) to reassign\n";

if ($grand_total === 0) {
    echo "\nNothing to do — no records found for customer " . FROM_CUSTOMER . " in tenant " . TENANT_ID . ".\n";
    exit(0);
}

if ($dry_run) {
    echo "\n=== DRY RUN — no changes made ===\n";
    echo "Append ?run=1 to the URL to execute the migration.\n";
    exit(0);
}

// ── Live run ─────────────────────────────────────────────────────────────────
echo "\n=== LIVE RUN ===\n\n";

try {
    $pdo->beginTransaction();

    foreach ($targets as $t) {
        $stmt = $pdo->prepare(
            "UPDATE `{$t['table']}` SET `{$t['column']}` = ? WHERE tenant_id = ? AND `{$t['column']}` = ?"
        );
        $stmt->execute([TO_CUSTOMER, TENANT_ID, FROM_CUSTOMER]);
        $rows = $stmt->rowCount();
        if ($rows > 0) {
            echo "  {$t['table']}.{$t['column']}: $rows row(s) reassigned " . FROM_CUSTOMER . " → " . TO_CUSTOMER . "\n";
        }
    }

    // ── Post-migration verification ──────────────────────────────────────────
    echo "\n=== POST-MIGRATION VERIFICATION ===\n\n";

    $fail = false;
    foreach ($targets as $t) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$t['table']}` WHERE tenant_id = ? AND `{$t['column']}` = ?"
        );
        $stmt->execute([TENANT_ID, FROM_CUSTOMER]);
        $remaining = (int)$stmt->fetchColumn();
        if ($remaining !== 0) {
            echo "FAIL: {$t['table']}.{$t['column']} still has $remaining row(s) referencing customer " . FROM_CUSTOMER . "\n";
            $fail = true;
        } else {
            echo "OK  : {$t['table']}.{$t['column']} — 0 rows remaining on source customer\n";
        }
    }

    if ($fail) {
        $pdo->rollBack();
        echo "\nRolled back: verification failed.\n";
        exit(1);
    }

    $pdo->commit();
    echo "\nCommitted successfully. Migration complete.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nRolled back due to error: " . $e->getMessage() . "\n";
    exit(1);
}
