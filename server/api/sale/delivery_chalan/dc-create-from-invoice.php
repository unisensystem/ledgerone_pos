<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

/**
 * Figure out the correct value to insert into delivery_chalan.status
 * without needing manual DB access. Works whether the column is:
 *  - ENUM('Draft','Confirmed',...)   -> picks the enum member that matches "draft"
 *  - VARCHAR/other                    -> falls back to a safe default
 */
function resolveDraftStatusValue(PDO $pdo, string $table = 'delivery_chalan', string $column = 'status'): string
{
    $preferred = 'draft'; // what we WANT logically, case-insensitive match
    $fallback  = 'Draft'; // used only if we can't detect anything

    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        $col = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($col && preg_match("/^enum\((.*)\)$/i", $col['Type'], $m)) {
            // Parse enum('Draft','Confirmed','Delivered') into an array
            preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $m[1], $vals);
            $options = $vals[1] ?? [];

            foreach ($options as $opt) {
                if (strtolower($opt) === $preferred) {
                    return $opt; // exact matching enum value, correct case
                }
            }

            // No exact "draft" match — just use the first defined option
            // so the insert never fails, and log it for visibility.
            if (!empty($options)) {
                error_log("delivery_chalan.status has no 'draft'-like value. Options found: " . implode(', ', $options) . ". Using '{$options[0]}' instead.");
                return $options[0];
            }
        }
    } catch (Exception $e) {
        error_log("resolveDraftStatusValue() failed: " . $e->getMessage());
    }

    return $fallback;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['invoice_id'])) {
        throw new Exception('invoice_id is required');
    }

    $invoiceId = (int) $input['invoice_id'];

    $pdo->beginTransaction();

    // Fetch the sale invoice (must belong to this tenant)
    $invStmt = $pdo->prepare("
        SELECT id, customer_id, branch_id
        FROM sale_invoice
        WHERE id = ? AND tenant_id = ?
    ");
    $invStmt->execute([$invoiceId, $tenant_id]);
    $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception('Sale invoice not found');
    }

    // Prevent creating a duplicate DC for the same invoice
    $existingStmt = $pdo->prepare("
        SELECT id, chalan_no FROM delivery_chalan
        WHERE tenant_id = ? AND sale_invoice_id = ?
        LIMIT 1
    ");
    $existingStmt->execute([$tenant_id, $invoiceId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $pdo->rollBack();
        echo json_encode([
            'success'    => true,
            'message'    => 'Delivery chalan already exists for this invoice',
            'chalan_id'  => $existing['id'],
            'chalan_no'  => $existing['chalan_no'],
            'already_existed' => true
        ]);
        exit;
    }

    // Fetch invoice items (skip rows that don't affect stock, e.g. informational rows)
    $itemsStmt = $pdo->prepare("
        SELECT product_id, uom_id, quantity, parent_row_id
        FROM sale_invoice_items
        WHERE sale_invoice_id = ? AND tenant_id = ?
    ");
    $itemsStmt->execute([$invoiceId, $tenant_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception('No items found on this invoice');
    }

    // Generate sequential chalan number tenant-wide
    $chalanStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(chalan_no, 4) AS UNSIGNED)) FROM delivery_chalan WHERE tenant_id = ? AND chalan_no LIKE 'DC-%'");
    $chalanStmt->execute([$tenant_id]);
    $lastNumber = (int) $chalanStmt->fetchColumn();
    $chalanNo = 'DC-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

    // Auto-detect the correct "draft" status value instead of hardcoding it
    $statusValue = resolveDraftStatusValue($pdo);

    // Insert delivery chalan header
    $dcStmt = $pdo->prepare("
        INSERT INTO delivery_chalan (
            tenant_id, chalan_no, delivery_date, customer_id, branch_id,
            sale_invoice_id, status, created_by, updated_by
        ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)
    ");
    $dcStmt->execute([
        $tenant_id,
        $chalanNo,
        $invoice['customer_id'],
        $invoice['branch_id'] ?? null,
        $invoiceId,
        $statusValue,
        $user_id,
        $user_id
    ]);

    $chalanId = $pdo->lastInsertId();

    // Insert delivery chalan items — one line per invoice item that carries a quantity
    $dciStmt = $pdo->prepare("
        INSERT INTO delivery_chalan_items (
            tenant_id, delivery_chalan_id, product_id, uom_id, quantity, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $qty = (float) $item['quantity'];
        if ($qty <= 0) continue;

        $dciStmt->execute([
            $tenant_id,
            $chalanId,
            $item['product_id'],
            $item['uom_id'],
            $qty,
            $user_id,
            $user_id
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success'   => true,
        'message'   => 'Delivery chalan created successfully',
        'chalan_id' => $chalanId,
        'chalan_no' => $chalanNo,
        'status_used' => $statusValue
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}