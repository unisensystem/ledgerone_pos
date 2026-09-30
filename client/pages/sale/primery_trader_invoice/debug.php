<?php
// ============================================================
// DEBUG TOOL - Batch/Expiry not showing issue
// Place this file in: client/pages/sale/primery_trader_invoice/debug.php
// Open in browser: http://localhost:8080/client/pages/sale/primery_trader_invoice/debug.php?product_id=3238
// DELETE THIS FILE after you are done debugging - it exposes internal data.
// ============================================================

require_once '../../../../includes/connection.php';

session_start();
$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

echo "<pre style='font-family:monospace;font-size:14px;background:#111;color:#0f0;padding:20px;'>";

echo "==================================================\n";
echo "1. SESSION CHECK\n";
echo "==================================================\n";
echo "user_id   = " . var_export($user_id, true) . "\n";
echo "tenant_id = " . var_export($tenant_id, true) . "\n";

if (!$user_id || !$tenant_id) {
    echo "\n>>> STOP: You are not logged in / session missing. Login first, then reload this page.\n";
    echo "</pre>";
    exit;
}

$product_id = $_GET['product_id'] ?? null;
$branch_id  = $_GET['branch_id'] ?? null;

echo "\nproduct_id (from URL) = " . var_export($product_id, true) . "\n";
echo "branch_id  (from URL) = " . var_export($branch_id, true) . "\n";

if (!$product_id) {
    echo "\n>>> Pass ?product_id=3238 in the URL to test a specific product.\n";
    echo "</pre>";
    exit;
}

echo "\n==================================================\n";
echo "2. PRODUCT INFO\n";
echo "==================================================\n";
$p = $pdo->prepare("SELECT id, tenant_id, code, name FROM products WHERE id = ?");
$p->execute([$product_id]);
$product = $p->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    echo "!!! Product ID $product_id does NOT exist in products table.\n";
} else {
    print_r($product);
    if ($product['tenant_id'] != $tenant_id) {
        echo "!!! MISMATCH: product's tenant_id ({$product['tenant_id']}) != session tenant_id ($tenant_id)\n";
        echo "    This alone would cause the batch query to return nothing.\n";
    }
}

echo "\n==================================================\n";
echo "3. ALL stock_ledger ROWS FOR THIS PRODUCT (raw, no filters)\n";
echo "==================================================\n";
$s = $pdo->prepare("SELECT id, tenant_id, branch_id, batch_no, expiry_date AS batch_expiry_date, qty_in, qty_out, transaction_date, transaction_type
                     FROM stock_ledger
                     WHERE product_id = ?
                     ORDER BY transaction_date, id");
$s->execute([$product_id]);
$rows = $s->fetchAll(PDO::FETCH_ASSOC);
echo "Total rows found: " . count($rows) . "\n\n";
foreach ($rows as $r) {
    echo "id={$r['id']} | tenant={$r['tenant_id']} | branch={$r['branch_id']} | batch=" . var_export($r['batch_no'], true)
       . " | expiry=" . var_export($r['batch_expiry_date'], true)
       . " | qty_in={$r['qty_in']} | qty_out={$r['qty_out']} | date={$r['transaction_date']} | type={$r['transaction_type']}\n";
}

echo "\n==================================================\n";
echo "4. GROUPED REMAINING QTY PER BATCH (tenant-filtered, matches endpoint logic)\n";
echo "==================================================\n";
$g = $pdo->prepare("
    SELECT batch_no, expiry_date AS batch_expiry_date, branch_id,
           SUM(COALESCE(qty_in,0)) - SUM(COALESCE(qty_out,0)) AS remaining_qty
    FROM stock_ledger
    WHERE tenant_id = ? AND product_id = ?
      AND batch_no IS NOT NULL AND batch_no != ''
    GROUP BY batch_no, expiry_date, branch_id
");
$g->execute([$tenant_id, $product_id]);
$grouped = $g->fetchAll(PDO::FETCH_ASSOC);
if (empty($grouped)) {
    echo "!!! NO GROUPS FOUND. This means either:\n";
    echo "    - batch_no is NULL/empty on all rows for this product, OR\n";
    echo "    - tenant_id on the stock_ledger rows does not match session tenant_id ($tenant_id)\n";
} else {
    foreach ($grouped as $gr) {
        $status = $gr['remaining_qty'] > 0 ? 'AVAILABLE' : 'EXHAUSTED (qty <= 0)';
        echo "batch={$gr['batch_no']} | branch={$gr['branch_id']} | remaining={$gr['remaining_qty']} | expiry={$gr['batch_expiry_date']} | $status\n";
    }
}

echo "\n==================================================\n";
echo "5. EXACT SAME QUERY AS get-product-batches.php (with branch filter if provided)\n";
echo "==================================================\n";
$sql = "
    SELECT
        batch_no,
        expiry_date AS batch_expiry_date,
        SUM(COALESCE(qty_in, 0)) - SUM(COALESCE(qty_out, 0)) AS remaining_qty,
        MIN(transaction_date) AS first_transaction_date,
        MIN(id) AS first_id
    FROM stock_ledger
    WHERE tenant_id = ?
      AND product_id = ?
      AND batch_no IS NOT NULL
      AND batch_no != ''
";
$params = [$tenant_id, $product_id];

if (!empty($branch_id)) {
    $sql .= " AND branch_id = ?";
    $params[] = $branch_id;
    echo "Branch filter IS applied (branch_id=$branch_id)\n";
} else {
    echo "Branch filter NOT applied (no branch_id passed in URL)\n";
}

$sql .= "
    GROUP BY batch_no, expiry_date
    HAVING remaining_qty > 0
    ORDER BY first_transaction_date ASC, first_id ASC
";

echo "\nSQL:\n$sql\n";
echo "Params: " . json_encode($params) . "\n\n";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "RESULT (" . count($batches) . " batch(es)):\n";
if (empty($batches)) {
    echo ">>> EMPTY. This is exactly what get-product-batches.php would return.\n";
    echo ">>> The Batch No / Expiry fields on the invoice will be blank because of this.\n";
} else {
    print_r($batches);
}

echo "\n==================================================\n";
echo "6. WHAT THE ACTUAL ENDPOINT RETURNS RIGHT NOW\n";
echo "==================================================\n";
$url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/../../../../server/api/sale/primery_trader_invoice/get-product-batches.php?product_id=$product_id";
if ($branch_id) $url .= "&branch_id=$branch_id";
echo "Simulated endpoint URL: $url\n";
echo "(Open Network tab on the invoice page and compare the real request to this)\n";

echo "\n==================================================\n";
echo "7. SUMMARY / LIKELY CAUSE\n";
echo "==================================================\n";
if (empty($rows)) {
    echo ">>> Product has ZERO stock_ledger rows at all. No stock has ever been posted for it.\n";
} elseif (empty($grouped)) {
    echo ">>> Product has stock_ledger rows, but none have a batch_no set (all NULL/empty),\n";
    echo "    or tenant_id mismatch. Batch tracking was never recorded for this product's stock.\n";
} elseif (empty($batches) && !empty($grouped)) {
    $anyPositive = false;
    foreach ($grouped as $gr) { if ($gr['remaining_qty'] > 0) $anyPositive = true; }
    if (!$anyPositive) {
        echo ">>> All batches for this product are EXHAUSTED (remaining_qty <= 0) tenant-wide.\n";
    } else {
        echo ">>> Batches exist with remaining stock, but the BRANCH FILTER excludes them.\n";
        echo "    The branch_id being sent from the invoice does not match the branch_id\n";
        echo "    stored on the stock_ledger rows. Re-check step 4 above: compare the\n";
        echo "    branch_id shown there against what the invoice's Branch dropdown sends.\n";
    }
} else {
    echo ">>> Batches ARE returned by this simulated query. If the invoice still shows blank,\n";
    echo "    the problem is NOT in the database/query - check Section 6 above and the\n";
    echo "    browser Network tab for the real request/response, or a JS error stopping\n";
    echo "    loadProductBatches() from running (check browser Console tab for red errors).\n";
}

echo "</pre>";