<?php
/**
 * Migration: Fix orphaned sub_account_id references for customer 80, tenant 5.
 *
 * Root cause: customer-edit.php used to DELETE all sub-accounts then re-INSERT them,
 * issuing new auto-increment IDs while existing transactions still referenced the old ones.
 *
 * Verified mapping (sequential re-insertion, confirmed by the one post-recreation invoice
 * that correctly uses new ID 21 for "Junaid D I Khan 2" which was old ID 12):
 *
 *   OLD ID  →  NEW ID  │  Sub-account name
 *   ───────────────────┼──────────────────────
 *      6    →    15    │  Abdul Rasheed
 *      7    →    16    │  Asif Jasra
 *      8    →    17    │  Imtiaz khan
 *      9    →    18    │  Muhammad Irfan
 *     10    →    19    │  Saleem Fathi pure
 *     11    →    20    │  Muhammad Khan
 *     12    →    21    │  Junaid D I Khan 2  ← confirmed
 *
 * Affected tables (from INFORMATION_SCHEMA scan + row counts):
 *   sale_invoice.sub_account_id       – 173 rows
 *   receive_voucher.sub_account_id    –  61 rows
 *   payment_voucher.sub_account_id    –   3 rows
 *   sale_return.sub_account_id        –   1 row
 *
 * Usage:
 *   DRY RUN (default, safe to run anytime):
 *     http://localhost/ledgerone_erp/database/migrations/fix_customer_sub_account_ids.php
 *
 *   LIVE RUN (writes to DB — wrap in transaction, rolls back on any error):
 *     http://localhost/ledgerone_erp/database/migrations/fix_customer_sub_account_ids.php?run=1
 */

require_once __DIR__ . '/../../includes/connection.php';

header('Content-Type: text/plain; charset=utf-8');

$dry_run = !(isset($_GET['run']) && $_GET['run'] === '1');

// ── Mapping: old_id => new_id ────────────────────────────────────────────────
$mapping = [
     6 => 15,   // Abdul Rasheed
     7 => 16,   // Asif Jasra
     8 => 17,   // Imtiaz khan
     9 => 18,   // Muhammad Irfan
    10 => 19,   // Saleem Fathi pure
    11 => 20,   // Muhammad Khan
    12 => 21,   // Junaid D I Khan 2
];

$old_ids = array_keys($mapping);
$placeholders = implode(',', array_fill(0, count($old_ids), '?'));

// ── Tables and their columns to fix ─────────────────────────────────────────
$targets = [
    ['table' => 'sale_invoice',    'column' => 'sub_account_id'],
    ['table' => 'receive_voucher', 'column' => 'sub_account_id'],
    ['table' => 'payment_voucher', 'column' => 'sub_account_id'],
    ['table' => 'sale_return',     'column' => 'sub_account_id'],
];

// ── Pre-flight: verify the new IDs actually exist and belong to customer 80 ──
echo "=== PRE-FLIGHT VERIFICATION ===\n\n";
$stmt = $pdo->prepare(
    "SELECT id, sub_account_name FROM customer_sub_accounts
     WHERE tenant_id = 5 AND customer_id = 80 AND id IN ($placeholders)
     ORDER BY id"
);
$stmt->execute(array_values($mapping));
$found = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$abort = false;
foreach ($mapping as $old => $new) {
    if (!isset($found[$new])) {
        echo "FAIL: new ID $new not found in customer_sub_accounts (expected for old ID $old)\n";
        $abort = true;
    } else {
        echo "OK  : old $old → new $new  [{$found[$new]}]\n";
    }
}

if ($abort) {
    echo "\nAborted: mapping verification failed. Do not proceed.\n";
    exit(1);
}

echo "\nAll new IDs verified.\n\n";

// ── Count affected rows per table ────────────────────────────────────────────
echo "=== AFFECTED ROW COUNTS ===\n\n";
$total = 0;
foreach ($targets as $t) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `{$t['table']}`
         WHERE tenant_id = 5 AND `{$t['column']}` IN ($placeholders)"
    );
    $stmt->execute($old_ids);
    $count = (int)$stmt->fetchColumn();
    $total += $count;
    echo sprintf("  %-35s %4d row(s)\n", "{$t['table']}.{$t['column']}", $count);
}
echo "\n  Total: $total row(s) to update\n\n";

if ($dry_run) {
    echo "=== DRY RUN — no changes made ===\n";
    echo "Append ?run=1 to the URL to execute the migration.\n";
    exit(0);
}

// ── Live run ─────────────────────────────────────────────────────────────────
echo "=== LIVE RUN ===\n\n";

try {
    $pdo->beginTransaction();

    foreach ($targets as $t) {
        $table  = $t['table'];
        $column = $t['column'];
        $updated_total = 0;

        foreach ($mapping as $old_id => $new_id) {
            $stmt = $pdo->prepare(
                "UPDATE `$table` SET `$column` = ?
                 WHERE tenant_id = 5 AND `$column` = ?"
            );
            $stmt->execute([$new_id, $old_id]);
            $rows = $stmt->rowCount();
            $updated_total += $rows;
            if ($rows > 0) {
                echo "  $table.$column: $old_id → $new_id  ($rows row(s))\n";
            }
        }

        echo "  [$table] total updated: $updated_total\n\n";
    }

    // ── Verify no orphaned old IDs remain ────────────────────────────────────
    echo "=== POST-MIGRATION VERIFICATION ===\n\n";
    $orphans_found = false;
    foreach ($targets as $t) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$t['table']}`
             WHERE tenant_id = 5 AND `{$t['column']}` IN ($placeholders)"
        );
        $stmt->execute($old_ids);
        $remaining = (int)$stmt->fetchColumn();
        if ($remaining > 0) {
            echo "FAIL: {$t['table']}.{$t['column']} still has $remaining orphaned row(s)\n";
            $orphans_found = true;
        } else {
            echo "OK  : {$t['table']}.{$t['column']} — 0 orphans remaining\n";
        }
    }

    if ($orphans_found) {
        $pdo->rollBack();
        echo "\nRolled back: orphans still present after update. Investigate before retrying.\n";
        exit(1);
    }

    $pdo->commit();
    echo "\nCommitted successfully. Migration complete.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nRolled back due to error: " . $e->getMessage() . "\n";
    exit(1);
}
