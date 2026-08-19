<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!isset($data['stock_data']) || !is_array($data['stock_data'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data format']);
        exit;
    }

    $stockData = $data['stock_data'];
    
    if (empty($stockData)) {
        echo json_encode(['success' => false, 'message' => 'No data to save']);
        exit;
    }

    // Default inventory account ID
    $inventory_account = 33;

    // Prepare statements
    $stockOpeningStmt = $pdo->prepare("
        INSERT INTO stock_opening (product_id, tenant_id, branch_id, opening_qty, opening_price, unit_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stockLedgerStmt = $pdo->prepare("
        INSERT INTO stock_ledger (tenant_id, account_id, branch_id, product_id, reference_table, reference_id, stock_status, qty_in, qty_out, unit_cost, unit_id, transaction_type, transaction_date)
        VALUES (?, ?, ?, ?, 'stock_opening', ?, 'sellable', ?, 0, ?, ?, 'Opening Stock', CURDATE())
    ");

    $saveCount = 0;
    $errors = [];

    // Debug logging
    error_log('=== BULK OPENING STOCK DEBUG ===');
    error_log('Total entries to process: ' . count($stockData));
    error_log('Data: ' . json_encode($stockData));

    foreach ($stockData as $index => $entry) {
        try {
            $product_id = $entry['product_id'] ?? null;
            $branch_id = $entry['branch_id'] ?? null;
            $opening_qty = floatval($entry['opening_qty'] ?? 0);
            $opening_price = floatval($entry['opening_price'] ?? 0);
            $unit_id = $entry['unit_id'] ?? null;

            if (!$product_id || !$branch_id) {
                $errors[] = "Entry $index: Missing product_id or branch_id";
                error_log("Entry $index: Missing product_id or branch_id");
                continue;
            }

            // Skip if no qty and no price
            if ($opening_qty == 0 && $opening_price == 0) {
                error_log("Entry $index: Skipped (no qty/price)");
                continue;
            }

            error_log("Entry $index: Processing product_id=$product_id, branch_id=$branch_id, qty=$opening_qty, price=$opening_price, unit_id=$unit_id");

            // Insert stock_opening
            $stockOpeningStmt->execute([
                $product_id,
                $tenant_id,
                $branch_id,
                $opening_qty,
                $opening_price,
                $unit_id
            ]);

            // Get the stock_opening ID immediately after insert
            $stock_opening_id = $pdo->lastInsertId();
            error_log("Entry $index: stock_opening inserted with ID=$stock_opening_id");

            // Insert stock ledger entry immediately after stock_opening
            $stockLedgerStmt->execute([
                $tenant_id,
                $inventory_account,
                $branch_id,
                $product_id,
                $stock_opening_id,
                $opening_qty,
                $opening_price,
                $unit_id
            ]);

            $saveCount++;
            error_log("Entry $index: stock_ledger inserted successfully");
            error_log("Entry $index: Saved successfully (stock_opening_id=$stock_opening_id, stock_ledger created)");

        } catch (Exception $e) {
            error_log("Entry $index: Error - " . $e->getMessage());
            $errors[] = "Entry $index: " . $e->getMessage();
        }
    }

    error_log("Total entries saved: $saveCount");
    error_log('============================');

    if ($saveCount > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully saved $saveCount opening stock entries",
            'count' => $saveCount,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No entries were saved. ' . (!empty($errors) ? implode('; ', $errors) : ''),
            'count' => 0,
            'errors' => $errors
        ]);
    }

} catch (PDOException $e) {
    error_log('BULK OPENING STOCK DB ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'count' => 0
    ]);
} catch (Exception $e) {
    error_log('BULK OPENING STOCK ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'count' => 0
    ]);
}
